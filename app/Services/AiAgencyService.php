<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
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
        throw_unless($run, RuntimeException::class, 'Analisi non trovata.');

        $snapshot = $this->buildProjectSnapshot($run->project_id);
        $modules = $this->decisionModules();
        $services = DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'description']);
        $input = $this->buildInput($snapshot, $modules, $services);
        $estimatedInputTokens = $this->estimateTokens($input);
        $remaining = $this->remainingBudget();
        $maximumEstimatedCost = $this->estimatedCost($estimatedInputTokens, config('ai-agency.max_output_tokens'), 2);

        if ($maximumEstimatedCost > $remaining) {
            throw new RuntimeException('Budget AI mensile insufficiente. Restano '.number_format($remaining, 2, ',', '.').' €.');
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
            $response = Http::withToken($this->apiKey())
                ->acceptJson()
                ->timeout(150)
                ->retry(2, 1000)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('ai-agency.model'),
                    'instructions' => $this->instructions(),
                    'input' => $input,
                    'tools' => [['type' => 'web_search']],
                    'max_output_tokens' => config('ai-agency.max_output_tokens'),
                    'text' => ['format' => $this->responseSchema()],
                ])
                ->throw()
                ->json();

            $outputPart = collect($response['output'] ?? [])
                ->where('type', 'message')
                ->flatMap(fn ($item) => $item['content'] ?? [])
                ->firstWhere('type', 'output_text');
            $outputText = $outputPart['text'] ?? null;
            $proposal = json_decode((string) $outputText, true, 512, JSON_THROW_ON_ERROR);
            $inputTokens = (int) data_get($response, 'usage.input_tokens', $estimatedInputTokens);
            $outputTokens = (int) data_get($response, 'usage.output_tokens', $this->estimateTokens((string) $outputText));
            $webSearches = collect($response['output'] ?? [])->where('type', 'web_search_call')->count();

            DB::transaction(function () use ($runId, $proposal, $inputTokens, $outputTokens, $webSearches) {
                DB::table('ai_agency_runs')->where('id', $runId)->update([
                    'status' => 'proposal_ready',
                    'proposal' => json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'web_searches' => $webSearches,
                    'estimated_cost_eur' => $this->estimatedCost($inputTokens, $outputTokens, $webSearches),
                    'updated_at' => now(),
                ]);

                DB::table('ai_agency_artifacts')->insert([
                    'id' => (string) Str::uuid(),
                    'run_id' => $runId,
                    'type' => 'analysis',
                    'title' => 'Analisi strategica',
                    'content' => json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $error) {
            DB::table('ai_agency_runs')->where('id', $runId)->update([
                'status' => 'error',
                'error_message' => Str::limit($error->getMessage(), 900),
                'updated_at' => now(),
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
                $children = DB::table('admin_modules')
                    ->where('parent_module_id', $mapping->workflow_id)
                    ->where('active', true)
                    ->orderBy('created_at')
                    ->get(['id', 'name', 'allowed_agents']);

                foreach ($children as $child) {
                    $agents = json_decode($child->allowed_agents ?: '[]', true) ?: [];
                    DB::table('ai_agency_steps')->insert([
                        'id' => (string) Str::uuid(),
                        'run_id' => $runId,
                        'service_id' => $mapping->service_id,
                        'workflow_module_id' => $mapping->workflow_id,
                        'module_id' => $child->id,
                        'name' => $child->name,
                        'agent_role' => $agents[0] ?? null,
                        'status' => $position === 0 ? 'todo' : 'blocked',
                        'position' => $position++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('ai_agency_runs')->where('id', $runId)->update([
                'status' => 'approved',
                'approved_services' => json_encode(array_values($serviceIds)),
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function executePmStrategy(string $runId): void
    {
        $run = DB::table('ai_agency_runs')->where('id', $runId)->first();
        throw_unless($run, RuntimeException::class, 'Processo non trovato.');
        $steps = DB::table('ai_agency_steps')->where('run_id', $runId)->orderBy('position')->get();
        throw_unless(($steps[0]->status ?? null) === 'completed', RuntimeException::class, 'Approva prima la raccolta informazioni.');
        throw_unless(($steps[1]->status ?? null) === 'todo', RuntimeException::class, 'La fase strategica non è disponibile.');

        $snapshot = $this->buildProjectSnapshot($run->project_id);
        $brief = json_decode($steps[0]->output_data ?: '{}', true) ?: [];
        $relevantNames = collect($brief['files'] ?? [])->where('assessment', 'relevant')->pluck('name');
        $snapshot['files'] = collect($snapshot['files'] ?? [])
            ->filter(fn ($file) => $relevantNames->contains($file['name'] ?? null))
            ->values()
            ->all();

        $moduleIds = $steps->slice(1, 3)->pluck('module_id')->filter();
        $modules = DB::table('admin_modules')->whereIn('id', $moduleIds)->get([
            'id', 'name', 'description', 'required_inputs', 'rules', 'output',
        ])->map(fn ($module) => [
            'name' => $module->name,
            'description' => $this->plainText($module->description),
            'required_inputs' => json_decode($module->required_inputs ?: '[]', true) ?: [],
            'rules' => $this->plainText($module->rules),
            'expected_output' => $this->plainText($module->output),
        ]);

        $input = json_encode([
            'project_snapshot' => $snapshot,
            'approved_project_brief' => $brief,
            'modules_to_execute_in_order' => $modules,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $estimatedInputTokens = $this->estimateTokens($input);
        $maximumEstimatedCost = $this->estimatedCost($estimatedInputTokens, config('ai-agency.max_output_tokens'), 3);
        if ($maximumEstimatedCost > $this->remainingBudget()) {
            throw new RuntimeException('Budget AI mensile insufficiente per la fase strategica.');
        }
        if (! $this->apiKey()) {
            throw new RuntimeException('Chiave OpenAI non configurata sul server.');
        }

        DB::table('ai_agency_steps')->whereIn('id', $steps->slice(1, 3)->pluck('id'))->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $response = Http::withToken($this->apiKey())
                ->acceptJson()
                ->timeout(180)
                ->retry(2, 1200)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('ai-agency.model'),
                    'instructions' => <<<'PROMPT'
Sei il Project Manager AI di LU3G. Esegui autonomamente, nell'ordine indicato, Analisi Cliente, Analisi Competitor e Definizione Strategia. Usa il brief approvato come fonte prioritaria rispetto alle informazioni precedenti in conflitto. Usa gli allegati pertinenti e svolgi ricerche web mirate per verificare azienda, dominio, mercato e competitor; non confondere omonimi e non inventare informazioni. Non chiedere input all'utente: evidenzia eventuali assunzioni e prosegui con la migliore analisi possibile. Ogni risultato deve essere un documento professionale, sintetico ma operativo, in italiano. La strategia deve integrare i risultati delle due analisi precedenti e fermarsi prima di sitemap, contenuti, design o sviluppo.
PROMPT,
                    'input' => $input,
                    'tools' => [['type' => 'web_search']],
                    'max_output_tokens' => config('ai-agency.max_output_tokens'),
                    'text' => ['format' => $this->pmStrategySchema()],
                ])->throw()->json();

            $outputPart = collect($response['output'] ?? [])->where('type', 'message')
                ->flatMap(fn ($item) => $item['content'] ?? [])->firstWhere('type', 'output_text');
            $result = json_decode((string) ($outputPart['text'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $inputTokens = (int) data_get($response, 'usage.input_tokens', $estimatedInputTokens);
            $outputTokens = (int) data_get($response, 'usage.output_tokens', $this->estimateTokens(json_encode($result)));
            $webSearches = collect($response['output'] ?? [])->where('type', 'web_search_call')->count();
            $documents = [
                1 => ['key' => 'client_analysis', 'type' => 'client_analysis', 'title' => 'Analisi Cliente', 'status' => 'completed'],
                2 => ['key' => 'competitor_analysis', 'type' => 'competitor_analysis', 'title' => 'Analisi Competitor', 'status' => 'completed'],
                3 => ['key' => 'strategy', 'type' => 'strategy', 'title' => 'Strategia', 'status' => 'approval'],
            ];

            DB::transaction(function () use ($runId, $run, $steps, $result, $documents, $snapshot, $inputTokens, $outputTokens, $webSearches) {
                foreach ($documents as $position => $document) {
                    $content = $result[$document['key']] ?? [];
                    DB::table('ai_agency_steps')->where('id', $steps[$position]->id)->update([
                        'status' => $document['status'],
                        'output_data' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'submitted_at' => now(),
                        'completed_at' => $document['status'] === 'completed' ? now() : null,
                        'updated_at' => now(),
                    ]);
                    DB::table('ai_agency_artifacts')->updateOrInsert(
                        ['run_id' => $runId, 'type' => $document['type']],
                        [
                            'id' => (string) Str::uuid(),
                            'title' => $document['title'],
                            'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }

                DB::table('ai_agency_runs')->where('id', $runId)->update([
                    'project_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'input_tokens' => (int) $run->input_tokens + $inputTokens,
                    'output_tokens' => (int) $run->output_tokens + $outputTokens,
                    'web_searches' => (int) $run->web_searches + $webSearches,
                    'estimated_cost_eur' => (float) $run->estimated_cost_eur + $this->estimatedCost($inputTokens, $outputTokens, $webSearches),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $error) {
            foreach ($steps->slice(1, 3)->values() as $index => $step) {
                DB::table('ai_agency_steps')->where('id', $step->id)->update([
                    'status' => $index === 0 ? 'todo' : 'blocked',
                    'updated_at' => now(),
                ]);
            }
            throw $error;
        }
    }

    public function approvePmStrategy(string $runId): void
    {
        $strategy = DB::table('ai_agency_steps')->where('run_id', $runId)->where('position', 3)->first();
        throw_unless($strategy && $strategy->status === 'approval', RuntimeException::class, 'La strategia non è pronta per l’approvazione.');

        DB::transaction(function () use ($runId, $strategy) {
            DB::table('ai_agency_steps')->where('id', $strategy->id)->update([
                'status' => 'completed', 'completed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ai_agency_steps')->where('run_id', $runId)->where('position', 4)->update([
                'status' => 'todo', 'updated_at' => now(),
            ]);
        });
    }

    public function remainingBudget(): float
    {
        $spent = (float) DB::table('ai_agency_runs')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('estimated_cost_eur');

        return max(0, (float) config('ai-agency.monthly_budget_eur') - $spent);
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    public function saveApiKey(string $key): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'ai_agency_openai'],
            [
                'id' => (string) Str::uuid(),
                'value' => json_encode(['encrypted_api_key' => Crypt::encryptString($key)]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function apiKey(): ?string
    {
        if (filled(config('ai-agency.api_key'))) {
            return config('ai-agency.api_key');
        }

        $raw = DB::table('app_settings')->where('key', 'ai_agency_openai')->value('value');
        $encrypted = data_get(json_decode((string) $raw, true), 'encrypted_api_key');

        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildProjectSnapshot(string $projectId): array
    {
        $project = DB::table('projects')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->where('projects.id', $projectId)
            ->first(['projects.*', 'clients.name as client_name', 'clients.business_sector', 'clients.website', 'clients.notes as client_notes']);
        throw_unless($project, RuntimeException::class, 'Progetto non trovato.');

        $tasks = DB::table('tasks')->where('project_id', $projectId)->orderBy('created_at')->get([
            'title', 'description', 'status', 'priority', 'task_type', 'start_date', 'due_date', 'parent_task_id',
        ]);
        $messages = DB::table('project_messages')->where('project_id', $projectId)->orderBy('created_at')->get(['content', 'created_at']);
        $files = DB::table('project_files')->where('project_id', $projectId)->orderBy('created_at')->get();

        return [
            'project' => [
                'name' => $project->name,
                'description' => $this->plainText($project->description),
                'status' => $project->status,
                'client' => $project->client_name,
                'sector' => $project->business_sector,
                'website' => $project->website,
                'client_notes' => $this->plainText($project->client_notes),
            ],
            'tasks' => $tasks->map(fn ($task) => [
                'title' => $task->title,
                'description' => $this->plainText($task->description),
                'status' => $task->status,
                'priority' => $task->priority,
                'type' => $task->task_type,
                'start' => $task->start_date,
                'due' => $task->due_date,
                'is_subtask' => filled($task->parent_task_id),
            ])->all(),
            'messages' => $messages->map(fn ($message) => $this->plainText($message->content))->filter()->values()->all(),
            'files' => $files->map(fn ($file) => [
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'content' => $this->extractFileText($file),
            ])->all(),
        ];
    }

    private function decisionModules(): Collection
    {
        return DB::table('admin_modules')
            ->join('admin_module_folders', 'admin_module_folders.id', '=', 'admin_modules.admin_module_folder_id')
            ->whereRaw('LOWER(admin_module_folders.name) = ?', ['decisioni'])
            ->where('admin_modules.active', true)
            ->orderBy('admin_modules.created_at')
            ->get(['admin_modules.name', 'admin_modules.description', 'admin_modules.required_inputs', 'admin_modules.rules', 'admin_modules.output'])
            ->map(fn ($module) => [
                'name' => $module->name,
                'description' => $this->plainText($module->description),
                'required_inputs' => json_decode($module->required_inputs ?: '[]', true) ?: [],
                'rules' => $this->plainText($module->rules),
                'expected_output' => $this->plainText($module->output),
            ]);
    }

    private function buildInput(array $snapshot, Collection $modules, Collection $services): string
    {
        return json_encode([
            'project_snapshot' => $snapshot,
            'lu3g_decision_modules' => $modules->all(),
            'available_services' => $services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $this->plainText($service->description),
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sei il Project Manager strategico di LU3G, un'agenzia web italiana. Analizza esclusivamente il materiale fornito sul progetto. I moduli Decisioni sono regole ed esperienza aziendale autorevole, ma non sono un elenco esaustivo: puoi formulare ragionamenti autonomi pertinenti, purché distingua chiaramente fatti, inferenze e informazioni mancanti. Usa la ricerca web solo quando serve a verificare sito, settore, competitor o informazioni attuali e cita le fonti. Non inventare dati. Consiglia soltanto servizi presenti in available_services e restituisci il loro id esatto. Non eseguire attività operative, non scegliere agenti e non descrivere la struttura del database. Produci una proposta concisa, concreta e utilizzabile per un'approvazione strategica. Scrivi sempre in italiano.
PROMPT;
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'lu3g_project_analysis',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'executive_summary' => ['type' => 'string'],
                    'recommended_services' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/service']],
                    'not_recommended_services' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/service']],
                    'priorities' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'roadmap' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'key_characteristics' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'missing_information' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'autonomous_inferences' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['executive_summary', 'recommended_services', 'not_recommended_services', 'priorities', 'roadmap', 'key_characteristics', 'missing_information', 'autonomous_inferences'],
                '$defs' => [
                    'service' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'service_id' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'motivation' => ['type' => 'string'],
                            'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        ],
                        'required' => ['service_id', 'name', 'motivation', 'confidence'],
                    ],
                ],
            ],
        ];
    }

    private function pmStrategySchema(): array
    {
        $document = [
            'type' => 'object',
            'additionalProperties' => false,
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

        return [
            'type' => 'json_schema',
            'name' => 'lu3g_pm_strategy',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'client_analysis' => $document,
                    'competitor_analysis' => $document,
                    'strategy' => $document,
                ],
                'required' => ['client_analysis', 'competitor_analysis', 'strategy'],
            ],
        ];
    }

    private function extractFileText(object $file): ?string
    {
        if (! Storage::disk('local')->exists($file->path)) {
            return null;
        }

        $path = Storage::disk('local')->path($file->path);
        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));

        if (in_array($extension, ['txt', 'md', 'csv', 'json'], true)) {
            return Str::limit((string) file_get_contents($path), 20000, '');
        }

        if ($extension === 'docx' && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                return Str::limit($this->plainText(str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], (string) $xml)), 20000, '');
            }
        }

        if ($extension === 'pdf') {
            try {
                return Str::limit((new PdfParser())->parseFile($path)->getText(), 30000, '');
            } catch (\Throwable) {
                return null;
            }
        }

        if ($extension === 'svg') {
            return Str::limit($this->plainText((string) file_get_contents($path)), 5000, '');
        }

        return null;
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
        return round(
            ($inputTokens / 1_000_000 * config('ai-agency.input_eur_per_million'))
            + ($outputTokens / 1_000_000 * config('ai-agency.output_eur_per_million'))
            + ($webSearches * config('ai-agency.web_search_eur_per_call')),
            4,
        );
    }
}
