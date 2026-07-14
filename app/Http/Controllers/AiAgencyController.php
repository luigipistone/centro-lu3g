<?php

namespace App\Http\Controllers;

use App\Services\AiAgencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            'projects' => DB::table('projects')->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                ->orderBy('projects.name')->get(['projects.id', 'projects.name', 'projects.color', 'clients.name as client_name']),
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
        $briefArtifact = DB::table('ai_agency_artifacts')->where('run_id', $id)->where('type', 'project_brief')->first();
        $brief = json_decode($briefArtifact->content ?? '{}', true) ?: [];
        $serviceIds = collect($proposal['recommended_services'] ?? [])->pluck('service_id')->merge($approvedServices)->filter()->unique();

        return Inertia::render('Centro/AiAgencyRun', [
            'run' => $run,
            'proposal' => $proposal,
            'brief' => $brief,
            'approvedServices' => $approvedServices,
            'services' => DB::table('services')->whereIn('id', $serviceIds)->get(['id', 'name', 'color']),
            'steps' => DB::table('ai_agency_steps')->leftJoin('services', 'services.id', '=', 'ai_agency_steps.service_id')
                ->where('ai_agency_steps.run_id', $id)->orderBy('ai_agency_steps.position')
                ->get(['ai_agency_steps.*', 'services.name as service_name', 'services.color as service_color']),
            'budget' => $this->budgetPayload(),
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

        return back()->with('status', DB::table('ai_agency_runs')->where('id', $id)->value('status') === 'needs_information'
            ? 'Servono alcune informazioni indispensabili per proseguire.'
            : 'Analisi completa pronta per l’approvazione.');
    }

    public function provideInformation(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $run = DB::table('ai_agency_runs')->where('id', $id)->first();
        abort_unless($run && $run->status === 'needs_information', 422);
        $proposal = json_decode($run->proposal ?: '{}', true) ?: [];
        $questions = data_get($proposal, 'readiness.blocking_questions', []);
        $payload = $request->validate(['answers' => ['required', 'array']]);
        if (collect($questions)->contains(fn ($question, $index) => blank($payload['answers'][$index] ?? null))) {
            return back()->withErrors(['information' => 'Completa tutte le informazioni indispensabili.']);
        }

        $brief = [
            'title' => 'Integrazione informazioni progetto',
            'questions' => collect($questions)->map(fn ($question, $index) => [
                'question' => $question,
                'answer' => $payload['answers'][$index],
            ])->values()->all(),
        ];
        DB::table('ai_agency_artifacts')->updateOrInsert(
            ['run_id' => $id, 'type' => 'project_brief'],
            ['id' => (string) Str::uuid(), 'title' => 'Brief di progetto', 'content' => json_encode($brief, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('ai_agency_runs')->where('id', $id)->update(['status' => 'draft', 'proposal' => null, 'updated_at' => now()]);

        try {
            $this->agency->analyze($id);
        } catch (\Throwable $error) {
            report($error);
            return back()->withErrors(['analysis' => $error->getMessage()]);
        }

        return back()->with('status', 'Informazioni integrate e analisi aggiornata.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $run = DB::table('ai_agency_runs')->where('id', $id)->first();
        abort_unless($run && $run->status === 'proposal_ready', 422);
        $allowedIds = DB::table('services')->where('active', true)->pluck('id')->all();
        $payload = $request->validate([
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['uuid', Rule::in($allowedIds)],
        ]);
        $this->agency->prepareWorkflows($id, $payload['service_ids']);

        return back()->with('status', 'Strategia approvata e workflow operativi preparati.');
    }

    public function executeNext(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        try {
            return response()->json($this->agency->executeNextStep($id));
        } catch (\Throwable $error) {
            report($error);
            return response()->json(['state' => 'error', 'message' => $error->getMessage(), 'continue' => false], 422);
        }
    }

    public function provideStepInformation(Request $request, string $id, string $stepId): JsonResponse
    {
        $this->authorizeAdmin($request);
        $payload = $request->validate(['answers' => ['required', 'array', 'min:1'], 'answers.*' => ['required', 'string', 'max:10000']]);

        try {
            $this->agency->provideStepInformation($id, $stepId, $payload['answers']);
            return response()->json(['state' => 'ready', 'continue' => true]);
        } catch (\Throwable $error) {
            return response()->json(['state' => 'error', 'message' => $error->getMessage(), 'continue' => false], 422);
        }
    }

    public function stepPdf(Request $request, string $id, string $stepId): BinaryFileResponse
    {
        $this->authorizeAdmin($request);
        $pdf = $this->agency->stepPdf($id, $stepId);

        return response()->file($pdf['path'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['name'].'"',
        ]);
    }

    public function configure(Request $request): RedirectResponse
    {
        abort_unless($this->role($request) === 'superadmin', 403);
        $payload = $request->validate(['api_key' => ['required', 'string', 'starts_with:sk-', 'min:20', 'max:500']]);
        $this->agency->saveApiKey($payload['api_key']);

        return back()->with('status', 'Connessione OpenAI configurata.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        DB::table('ai_agency_runs')->where('id', $id)->delete();
        return redirect()->route('ai-agency.index')->with('status', 'Processo eliminato.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($this->role($request), ['admin', 'superadmin'], true), 403);
    }

    private function role(Request $request): string
    {
        return (string) DB::table('user_roles')->where('user_id', $request->user()->id)->value('role');
    }

    private function runQuery()
    {
        return DB::table('ai_agency_runs')->join('projects', 'projects.id', '=', 'ai_agency_runs.project_id')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')->leftJoin('users', 'users.id', '=', 'ai_agency_runs.created_by')
            ->latest('ai_agency_runs.created_at')->select(['ai_agency_runs.*', 'projects.name as project_name', 'projects.color as project_color', 'clients.name as client_name', 'users.name as creator_name']);
    }

    private function budgetPayload(): array
    {
        $limit = (float) config('ai-agency.monthly_budget_eur');
        $remaining = $this->agency->remainingBudget();
        return ['limit' => $limit, 'spent' => round($limit - $remaining, 4), 'remaining' => round($remaining, 4)];
    }
}
