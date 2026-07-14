<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class AiAgencyService
{
    public function analyze(string $runId): void
    {
        $run = DB::table('ai_agency_runs')->where('id', $runId)->first();
        throw_unless($run, RuntimeException::class, 'Processo non trovato.');
        $snapshot = $this->buildProjectSnapshot($run->project_id);
        $brief = $this->artifactContent($runId, 'project_brief');
        $input = json_encode([
            'project_snapshot' => $snapshot,
            'approved_or_integrated_brief' => $brief,
            'decision_modules' => $this->decisionModules()->all(),
            'available_services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'description']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $estimatedInputTokens = $this->estimateTokens($input);
        if ($this->estimatedCost($estimatedInputTokens, config('ai-agency.max_output_tokens'), 3) > $this->remainingBudget()) {
            throw new RuntimeException('Budget AI mensile insufficiente per completare l’analisi.');
        }
        if (! $this->apiKey()) {
            throw new RuntimeException('Chiave OpenAI non configurata sul server.');
        }

        DB::table('ai_agency_runs')->where('id', $runId)->update([
            'status' => 'analyzing',
            'project_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'model' => config('ai-agency.model'),
            'error_message' => null,
            'updated_at' => now(),
        ]);

        try {
            $response = Http::withToken($this->apiKey())->acceptJson()->timeout(210)->retry(2, 1200)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('ai-agency.model'),
                    'instructions' => $this->analysisInstructions(),
                    'input' => $input,
                    'tools' => [['type' => 'web_search']],
                    'max_output_tokens' => config('ai-agency.max_output_tokens'),
                    'text' => ['format' => $this->analysisSchema()],
                ])->throw()->json();

            $outputPart = collect($response['output'] ?? [])->where('type', 'message')
                ->flatMap(fn ($item) => $item['content'] ?? [])->firstWhere('type', 'output_text');
            $analysis = json_decode((string) ($outputPart['text'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $inputTokens = (int) data_get($response, 'usage.input_tokens', $estimatedInputTokens);
            $outputTokens = (int) data_get($response, 'usage.output_tokens', $this->estimateTokens(json_encode($analysis)));
            $webSearches = collect($response['output'] ?? [])->where('type', 'web_search_call')->count();
            $ready = (bool) data_get($analysis, 'readiness.ready', false);

            DB::transaction(function () use ($runId, $run, $analysis, $snapshot, $inputTokens, $outputTokens, $webSearches, $ready) {
                DB::table('ai_agency_runs')->where('id', $runId)->update([
                    'status' => $ready ? 'proposal_ready' : 'needs_information',
                    'project_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'proposal' => json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'input_tokens' => (int) $run->input_tokens + $inputTokens,
                    'output_tokens' => (int) $run->output_tokens + $outputTokens,
                    'web_searches' => (int) $run->web_searches + $webSearches,
                    'estimated_cost_eur' => (float) $run->estimated_cost_eur + $this->estimatedCost($inputTokens, $outputTokens, $webSearches),
                    'updated_at' => now(),
                ]);

                $this->storeArtifact($runId, 'source_assessment', 'Verifica delle fonti', $analysis['readiness'] ?? []);
                if ($ready) {
                    $this->storeArtifact($runId, 'client_analysis', 'Analisi Cliente', $analysis['client_analysis'] ?? []);
                    $this->storeArtifact($runId, 'competitor_analysis', 'Analisi Competitor', $analysis['competitor_analysis'] ?? []);
                    $this->storeArtifact($runId, 'strategy', 'Strategia e servizi', $analysis['strategy'] ?? []);
                }
            });
        } catch (\Throwable $error) {
            DB::table('ai_agency_runs')->where('id', $runId)->update([
                'status' => 'error', 'error_message' => Str::limit($error->getMessage(), 900), 'updated_at' => now(),
            ]);
            throw $error;
        }
    }

    public function prepareWorkflows(string $runId, array $serviceIds): void
    {
        DB::transaction(function () use ($runId, $serviceIds) {
            DB::table('ai_agency_steps')->where('run_id', $runId)->delete();
            $position = 0;
            $mappings = DB::table('ai_agency_service_workflows')
                ->join('admin_modules', 'admin_modules.id', '=', 'ai_agency_service_workflows.workflow_module_id')
                ->whereIn('ai_agency_service_workflows.service_id', $serviceIds)
                ->get(['ai_agency_service_workflows.service_id', 'admin_modules.id as workflow_id', 'admin_modules.name']);

            foreach ($mappings as $mapping) {
                $children = DB::table('admin_modules')->where('parent_module_id', $mapping->workflow_id)
                    ->where('active', true)->orderBy('created_at')->get(['id', 'name', 'allowed_agents']);
                foreach ($children as $child) {
                    $agents = json_decode($child->allowed_agents ?: '[]', true) ?: [];
                    DB::table('ai_agency_steps')->insert([
                        'id' => (string) Str::uuid(), 'run_id' => $runId, 'service_id' => $mapping->service_id,
                        'workflow_module_id' => $mapping->workflow_id, 'module_id' => $child->id, 'name' => $child->name,
                        'agent_role' => $agents[0] ?? null, 'status' => $position === 0 ? 'todo' : 'blocked',
                        'position' => $position++, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            DB::table('ai_agency_runs')->where('id', $runId)->update([
                'status' => 'approved', 'approved_services' => json_encode(array_values($serviceIds)),
                'approved_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function remainingBudget(): float
    {
        $spent = (float) DB::table('ai_agency_runs')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('estimated_cost_eur');
        return max(0, (float) config('ai-agency.monthly_budget_eur') - $spent);
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    public function saveApiKey(string $key): void
    {
        DB::table('app_settings')->updateOrInsert(['key' => 'ai_agency_openai'], [
            'id' => (string) Str::uuid(), 'value' => json_encode(['encrypted_api_key' => Crypt::encryptString($key)]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function analysisInstructions(): string
    {
        return <<<'PROMPT'
Sei il Project Manager AI di LU3G. Devi seguire rigorosamente questo ordine: 1) leggere progetto, brief e allegati; 2) verificare identità, coerenza e sufficienza delle fonti; 3) se mancano informazioni davvero bloccanti, fermarti e formulare solo poche domande indispensabili; 4) se i dati sono sufficienti, produrre Analisi Cliente; 5) svolgere Analisi Competitor con ricerca web mirata e fonti; 6) solo dopo applicare i moduli Decisioni LU3G e generare strategia, servizi consigliati e non consigliati, priorità e roadmap. Il brief approvato prevale su dati precedenti in conflitto. Non confondere omonimi, non inventare informazioni e distingui fatti, assunzioni e fonti. Non proporre servizi prima di aver completato le analisi. Consiglia solo servizi presenti in available_services usando il loro id esatto. Scrivi in italiano. Se readiness.ready è false, lascia vuoti analisi, strategia e servizi: non prendere decisioni premature.
PROMPT;
    }

    private function analysisSchema(): array
    {
        $document = [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => 'string'],
                'findings' => ['type' => 'array', 'items' => ['type' => 'string']],
                'recommendations' => ['type' => 'array', 'items' => ['type' => 'string']],
                'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                'assumptions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['summary', 'findings', 'recommendations', 'risks', 'assumptions', 'sources'],
        ];
        $service = [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'service_id' => ['type' => 'string'], 'name' => ['type' => 'string'],
                'motivation' => ['type' => 'string'], 'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            ],
            'required' => ['service_id', 'name', 'motivation', 'confidence'],
        ];

        return [
            'type' => 'json_schema', 'name' => 'lu3g_validated_project_analysis', 'strict' => true,
            'schema' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'readiness' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => [
                            'ready' => ['type' => 'boolean'],
                            'summary' => ['type' => 'string'],
                            'blocking_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'conflicts' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'document_assessments' => ['type' => 'array', 'items' => [
                                'type' => 'object', 'additionalProperties' => false,
                                'properties' => ['name' => ['type' => 'string'], 'assessment' => ['type' => 'string'], 'reason' => ['type' => 'string']],
                                'required' => ['name', 'assessment', 'reason'],
                            ]],
                        ],
                        'required' => ['ready', 'summary', 'blocking_questions', 'conflicts', 'document_assessments'],
                    ],
                    'client_analysis' => $document,
                    'competitor_analysis' => $document,
                    'strategy' => $document,
                    'recommended_services' => ['type' => 'array', 'items' => $service],
                    'not_recommended_services' => ['type' => 'array', 'items' => $service],
                    'priorities' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'roadmap' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['readiness', 'client_analysis', 'competitor_analysis', 'strategy', 'recommended_services', 'not_recommended_services', 'priorities', 'roadmap'],
            ],
        ];
    }

    private function buildProjectSnapshot(string $projectId): array
    {
        $project = DB::table('projects')->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->where('projects.id', $projectId)->first(['projects.*', 'clients.name as client_name', 'clients.business_sector', 'clients.website', 'clients.notes as client_notes']);
        throw_unless($project, RuntimeException::class, 'Progetto non trovato.');
        $tasks = DB::table('tasks')->where('project_id', $projectId)->orderBy('created_at')->get(['title', 'description', 'status', 'priority', 'task_type', 'start_date', 'due_date', 'parent_task_id']);
        $messages = DB::table('project_messages')->where('project_id', $projectId)->orderBy('created_at')->pluck('content');
        $files = DB::table('project_files')->where('project_id', $projectId)->orderBy('created_at')->get();

        return [
            'project' => ['name' => $project->name, 'description' => $this->plainText($project->description), 'status' => $project->status,
                'client' => $project->client_name, 'sector' => $project->business_sector, 'website' => $project->website, 'client_notes' => $this->plainText($project->client_notes)],
            'tasks' => $tasks->map(fn ($task) => ['title' => $task->title, 'description' => $this->plainText($task->description), 'status' => $task->status,
                'priority' => $task->priority, 'type' => $task->task_type, 'start' => $task->start_date, 'due' => $task->due_date, 'is_subtask' => filled($task->parent_task_id)])->all(),
            'messages' => $messages->map(fn ($message) => $this->plainText($message))->filter()->values()->all(),
            'files' => $files->map(fn ($file) => ['name' => $file->original_name, 'mime_type' => $file->mime_type, 'content' => $this->extractFileText($file)])->all(),
        ];
    }

    private function decisionModules(): Collection
    {
        return DB::table('admin_modules')->join('admin_module_folders', 'admin_module_folders.id', '=', 'admin_modules.admin_module_folder_id')
            ->whereRaw('LOWER(admin_module_folders.name) = ?', ['decisioni'])->where('admin_modules.active', true)->orderBy('admin_modules.created_at')
            ->get(['admin_modules.name', 'admin_modules.description', 'admin_modules.required_inputs', 'admin_modules.rules', 'admin_modules.output'])
            ->map(fn ($module) => ['name' => $module->name, 'description' => $this->plainText($module->description),
                'required_inputs' => json_decode($module->required_inputs ?: '[]', true) ?: [], 'rules' => $this->plainText($module->rules), 'expected_output' => $this->plainText($module->output)]);
    }

    private function extractFileText(object $file): ?string
    {
        if (! Storage::disk('local')->exists($file->path)) return null;
        $path = Storage::disk('local')->path($file->path);
        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        try {
            if (in_array($extension, ['txt', 'md', 'csv', 'json'], true)) return Str::limit((string) file_get_contents($path), 30000, '');
            if ($extension === 'pdf') return Str::limit((new PdfParser())->parseFile($path)->getText(), 30000, '');
            if ($extension === 'svg') return Str::limit($this->plainText((string) file_get_contents($path)), 5000, '');
            if ($extension === 'docx' && class_exists(ZipArchive::class)) {
                $zip = new ZipArchive();
                if ($zip->open($path) === true) {
                    $xml = $zip->getFromName('word/document.xml'); $zip->close();
                    return Str::limit($this->plainText(str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], (string) $xml)), 30000, '');
                }
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    private function storeArtifact(string $runId, string $type, string $title, array $content): void
    {
        DB::table('ai_agency_artifacts')->updateOrInsert(['run_id' => $runId, 'type' => $type], [
            'id' => (string) Str::uuid(), 'title' => $title,
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function artifactContent(string $runId, string $type): array
    {
        return json_decode((string) DB::table('ai_agency_artifacts')->where('run_id', $runId)->where('type', $type)->value('content'), true) ?: [];
    }

    private function apiKey(): ?string
    {
        if (filled(config('ai-agency.api_key'))) return config('ai-agency.api_key');
        $raw = DB::table('app_settings')->where('key', 'ai_agency_openai')->value('value');
        $encrypted = data_get(json_decode((string) $raw, true), 'encrypted_api_key');
        if (! $encrypted) return null;
        try { return Crypt::decryptString($encrypted); } catch (\Throwable) { return null; }
    }

    private function plainText(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
    }

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 3.5));
    }

    private function estimatedCost(int $inputTokens, int $outputTokens, int $webSearches): float
    {
        return round(($inputTokens / 1_000_000 * config('ai-agency.input_eur_per_million'))
            + ($outputTokens / 1_000_000 * config('ai-agency.output_eur_per_million'))
            + ($webSearches * config('ai-agency.web_search_eur_per_call')), 4);
    }
}
