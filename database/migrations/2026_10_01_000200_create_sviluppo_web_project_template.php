<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('project_templates')->where('name', 'Modello Sviluppo Web')->exists()) {
            return;
        }

        $people = DB::table('users')->whereIn('name', [
            'Giulia Grandi', 'Giorgio Sala', 'Francesco Puglisi', 'Andrea Riva',
        ])->pluck('id', 'name');
        $person = fn (string $name): array => isset($people[$name]) ? [$people[$name]] : [];
        $subtask = fn (string $title, ?string $assignee = null, ?int $day = null, int $duration = 1): array => [
            'title' => $title,
            'assignee_ids' => $assignee ? $person($assignee) : [],
            'day_offset' => $day,
            'duration_days' => $duration,
        ];

        $sections = [
            'Fase Preliminare' => [
                ['email', 'Inviare Email per presa in carico e richieste riunione', 'Giulia Grandi', 0, 3, 'medium', [], 'Ricordarsi di allegare "Documento di dettaglio Sito Web"', [
                    $subtask('Riunione con report'),
                    $subtask('Recap via email della riunione'),
                    $subtask('Call Aggiuntiva Testi'),
                ]],
                ['team', 'Riunione con il team sviluppo', 'Giulia Grandi', 10, 1, 'medium', ['email']],
            ],
            'Fase Realizzativa' => [
                ['figma', 'Sviluppo mockup con Figma', 'Giulia Grandi', 11, 6, 'medium', ['team']],
                ['testi', 'Conferma o modifica dei testi', 'Giorgio Sala', 21, 6, 'medium', []],
                ['sviluppo', 'Sviluppo del sito web online', 'Francesco Puglisi', 26, 8, 'medium', ['figma', 'testi']],
            ],
            'Fase Conclusiva' => [
                ['modifiche', 'Realizzazione modifiche per max 2 tornate', 'Francesco Puglisi', 50, 1, 'medium', []],
                ['controllo', 'Controllo e Responsive', 'Francesco Puglisi', 57, 1, 'medium', ['modifiche'], null, [
                    $subtask('Creazione delle Thank You Page'),
                    $subtask('Testing di tutte le funzionalità esistenti sul sito'),
                    $subtask('Ottimizzare il sito per dispositivi mobili'),
                    $subtask('Tradurre tutte le voci in inglese rimaste dai plugin'),
                    $subtask('Controllare il corretto funzionamento dei form'),
                    $subtask('Installare snippet Google Analytics sul sito web'),
                    $subtask('Installare snippet Facebook Pixel sul sito web'),
                    $subtask('Configurare la privacy policy e cookie policy'),
                    $subtask('Inserire il flag per la privacy policy in tutti i form presenti'),
                    $subtask('Configurare e impostare la ricezione contatti con iscrizione newsletter'),
                    $subtask('SE ECOMMERCE: Configurare metodi di pagamento, spedizioni e altri dettagli'),
                    $subtask('Rimuovere la spunta "Scoraggia i motori di ricerca"'),
                    $subtask('Impostare la SEO di base', null, 54, 4),
                ]],
                ['online', 'Messa online del sito web', 'Francesco Puglisi', 60, 1, 'medium', ['controllo']],
                ['tracciamenti', 'Impostare tracciamenti', 'Andrea Riva', 63, 1, 'high', ['online']],
            ],
        ];

        DB::transaction(function () use ($sections, $person) {
            $templateId = (string) Str::uuid();
            DB::table('project_templates')->insert([
                'id' => $templateId,
                'name' => 'Modello Sviluppo Web',
                'description' => null,
                'color' => '#2563eb',
                'active' => true,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($sections as $sectionIndex => $tasks) {
                $sectionId = (string) Str::uuid();
                DB::table('project_template_sections')->insert([
                    'id' => $sectionId,
                    'project_template_id' => $templateId,
                    'name' => $sectionIndex,
                    'position' => array_search($sectionIndex, array_keys($sections), true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($tasks as $position => $task) {
                    [$key, $title, $assignee, $day, $duration, $priority, $blockedBy] = $task;
                    DB::table('project_template_tasks')->insert([
                        'id' => (string) Str::uuid(),
                        'template_key' => $key,
                        'project_template_section_id' => $sectionId,
                        'title' => $title,
                        'description' => $task[7] ?? null,
                        'service_id' => null,
                        'assignee_ids' => json_encode($person($assignee)),
                        'subtasks' => json_encode($task[8] ?? []),
                        'day_offset' => $day,
                        'date_offset_direction' => 'after',
                        'date_reference_type' => 'project_start',
                        'date_reference_task_key' => null,
                        'dependency_mode' => $blockedBy ? 'blocked_by' : 'none',
                        'dependency_task_keys' => json_encode($blockedBy),
                        'duration_days' => $duration,
                        'due_time' => null,
                        'location' => null,
                        'priority' => $priority,
                        'task_type' => 'project',
                        'status' => 'in_progress',
                        'position' => $position,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Keep user-edited templates and projects created from this template.
    }
};
