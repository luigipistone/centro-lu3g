<?php

namespace App\Http\Controllers;

use App\Services\AiAgencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiAgencyController extends Controller
{
    public function __construct(private readonly AiAgencyService $agency) {}

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('Centro/AiAgency', [
            'projects' => DB::table('projects')
                ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                ->orderBy('projects.name')
                ->get(['projects.id', 'projects.name', 'projects.color', 'clients.name as client_name']),
            'runs' => $this->runQuery()->limit(30)->get(),
            'budget' => $this->budgetPayload(),
            'configured' => $this->agency->isConfigured(),
            'canConfigure' => $this->role($request) === 'superadmin',
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizeAdmin($request);
        $run = $this->runQuery()->where('ai_agency_runs.id', $id)->first();
        abort_unless($run, 404);

        $proposal = json_decode($run->proposal ?: '{}', true) ?: [];
        $approvedServices = json_decode($run->approved_services ?: '[]', true) ?: [];
        $serviceIds = collect($proposal['recommended_services'] ?? [])->pluck('service_id')->merge($approvedServices)->filter()->unique();

        return Inertia::render('Centro/AiAgencyRun', [
            'run' => $run,
            'proposal' => $proposal,
            'approvedServices' => $approvedServices,
            'services' => DB::table('services')->whereIn('id', $serviceIds)->get(['id', 'name', 'color']),
            'steps' => DB::table('ai_agency_steps')
                ->leftJoin('services', 'services.id', '=', 'ai_agency_steps.service_id')
                ->where('ai_agency_steps.run_id', $id)
                ->orderBy('ai_agency_steps.position')
                ->get(['ai_agency_steps.*', 'services.name as service_name', 'services.color as service_color']),
            'workflowMappings' => DB::table('ai_agency_service_workflows')
                ->join('admin_modules', 'admin_modules.id', '=', 'ai_agency_service_workflows.workflow_module_id')
                ->whereIn('ai_agency_service_workflows.service_id', $serviceIds)
                ->pluck('admin_modules.name', 'ai_agency_service_workflows.service_id'),
            'budget' => $this->budgetPayload(),
            'projectFiles' => collect(json_decode($run->project_snapshot ?: '{}', true)['files'] ?? [])->map(fn ($file, $index) => [
                'id' => (string) $index,
                'name' => $file['name'] ?? 'Allegato',
                'mime_type' => $file['mime_type'] ?? null,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $payload = $request->validate(['project_id' => ['required', 'uuid', 'exists:projects,id']]);
        $id = (string) Str::uuid();

        DB::table('ai_agency_runs')->insert([
            'id' => $id,
            'project_id' => $payload['project_id'],
            'created_by' => $request->user()->id,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('ai-agency.show', $id);
    }

    public function analyze(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        DB::table('ai_agency_runs')->where('id', $id)->exists() || abort(404);

        try {
            $this->agency->analyze($id);
        } catch (\Throwable $error) {
            report($error);

            return back()->withErrors(['analysis' => $error->getMessage()]);
        }

        return back()->with('status', 'Analisi completata.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $run = DB::table('ai_agency_runs')->where('id', $id)->first();
        abort_unless($run, 404);
        abort_unless($run->status === 'proposal_ready', 422);

        $allowedIds = DB::table('services')->where('active', true)->pluck('id')->all();
        $payload = $request->validate([
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['uuid', Rule::in($allowedIds)],
        ]);

        $this->agency->prepareWorkflows($id, $payload['service_ids']);

        return back()->with('status', 'Strategia approvata e workflow preparati.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        DB::table('ai_agency_runs')->where('id', $id)->delete();

        return redirect()->route('ai-agency.index')->with('status', 'Analisi eliminata.');
    }

    public function startStep(Request $request, string $runId, string $stepId): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $step = $this->step($runId, $stepId);
        abort_unless($step->position === 0 && $step->status === 'todo', 422);

        DB::table('ai_agency_steps')->where('id', $stepId)->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Raccolta informazioni avviata.');
    }

    public function saveStep(Request $request, string $runId, string $stepId): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $step = $this->step($runId, $stepId);
        abort_unless($step->position === 0 && in_array($step->status, ['in_progress', 'approval'], true), 422);
        $payload = $request->validate([
            'answers' => ['present', 'array'],
            'answers.*' => ['nullable', 'string', 'max:10000'],
            'file_assessments' => ['present', 'array'],
            'file_assessments.*' => [Rule::in(['relevant', 'uncertain', 'irrelevant'])],
        ]);

        DB::table('ai_agency_steps')->where('id', $stepId)->update([
            'input_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'in_progress',
            'submitted_at' => null,
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Brief salvato.');
    }

    public function submitStep(Request $request, string $runId, string $stepId): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $step = $this->step($runId, $stepId);
        abort_unless($step->position === 0 && $step->status === 'in_progress', 422);
        $input = json_decode($step->input_data ?: '{}', true) ?: [];
        $run = DB::table('ai_agency_runs')->where('id', $runId)->first();
        $proposal = json_decode($run->proposal ?: '{}', true) ?: [];
        $questions = $proposal['missing_information'] ?? [];
        $answers = $input['answers'] ?? [];
        $files = json_decode($run->project_snapshot ?: '{}', true)['files'] ?? [];
        $fileAssessments = $input['file_assessments'] ?? [];

        if (collect($questions)->contains(fn ($question, $index) => blank($answers[$index] ?? null))) {
            return back()->withErrors(['step' => 'Completa tutte le informazioni richieste prima di inviare il brief.']);
        }
        if (count($fileAssessments) < count($files)) {
            return back()->withErrors(['step' => 'Classifica tutti gli allegati prima di inviare il brief.']);
        }

        $output = [
            'title' => 'Brief di progetto',
            'questions' => collect($questions)->map(fn ($question, $index) => [
                'question' => $question,
                'answer' => $answers[$index] ?? '',
            ])->values()->all(),
            'files' => collect($files)->map(fn ($file, $index) => [
                'name' => $file['name'] ?? 'Allegato',
                'assessment' => $fileAssessments[(string) $index] ?? $fileAssessments[$index] ?? 'uncertain',
            ])->values()->all(),
        ];

        DB::table('ai_agency_steps')->where('id', $stepId)->update([
            'status' => 'approval',
            'output_data' => json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Brief pronto per l’approvazione.');
    }

    public function approveStep(Request $request, string $runId, string $stepId): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $step = $this->step($runId, $stepId);
        abort_unless($step->position === 0 && $step->status === 'approval', 422);

        DB::transaction(function () use ($runId, $stepId, $step) {
            DB::table('ai_agency_artifacts')->updateOrInsert(
                ['run_id' => $runId, 'type' => 'project_brief'],
                [
                    'id' => (string) Str::uuid(),
                    'title' => 'Brief di progetto',
                    'content' => $step->output_data,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            DB::table('ai_agency_steps')->where('id', $stepId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ai_agency_steps')
                ->where('run_id', $runId)
                ->where('position', '>', $step->position)
                ->orderBy('position')
                ->limit(1)
                ->update(['status' => 'todo', 'updated_at' => now()]);
        });

        return back()->with('status', 'Brief approvato. Il modulo successivo è ora disponibile.');
    }

    public function executePmStrategy(Request $request, string $runId): RedirectResponse
    {
        $this->authorizeAdmin($request);

        try {
            $this->agency->executePmStrategy($runId);
        } catch (\Throwable $error) {
            report($error);

            return back()->withErrors(['strategy' => $error->getMessage()]);
        }

        return back()->with('status', 'Fase strategica completata. La strategia è pronta per l’approvazione.');
    }

    public function approvePmStrategy(Request $request, string $runId): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->agency->approvePmStrategy($runId);

        return back()->with('status', 'Strategia approvata. La creazione della sitemap è ora disponibile.');
    }

    public function configure(Request $request): RedirectResponse
    {
        abort_unless($this->role($request) === 'superadmin', 403);
        $payload = $request->validate([
            'api_key' => ['required', 'string', 'starts_with:sk-', 'min:20', 'max:500'],
        ]);
        $this->agency->saveApiKey($payload['api_key']);

        return back()->with('status', 'Connessione OpenAI configurata.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($this->role($request), ['admin', 'superadmin'], true), 403);
    }

    private function role(Request $request): string
    {
        return (string) DB::table('user_roles')->where('user_id', $request->user()->id)->value('role');
    }

    private function step(string $runId, string $stepId): object
    {
        $step = DB::table('ai_agency_steps')->where('run_id', $runId)->where('id', $stepId)->first();
        abort_unless($step, 404);

        return $step;
    }

    private function runQuery()
    {
        return DB::table('ai_agency_runs')
            ->join('projects', 'projects.id', '=', 'ai_agency_runs.project_id')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->leftJoin('users', 'users.id', '=', 'ai_agency_runs.created_by')
            ->latest('ai_agency_runs.created_at')
            ->select([
                'ai_agency_runs.*',
                'projects.name as project_name',
                'projects.color as project_color',
                'clients.name as client_name',
                'users.name as creator_name',
            ]);
    }

    private function budgetPayload(): array
    {
        $limit = (float) config('ai-agency.monthly_budget_eur');
        $remaining = $this->agency->remainingBudget();

        return [
            'limit' => $limit,
            'spent' => round($limit - $remaining, 4),
            'remaining' => round($remaining, 4),
        ];
    }
}
