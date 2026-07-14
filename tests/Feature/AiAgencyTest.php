<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiAgencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_ai_agency_analysis_for_a_project(): void
    {
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);

        $response = $this->actingAs($admin)->post(route('ai-agency.store'), [
            'project_id' => $projectId,
        ]);

        $run = DB::table('ai_agency_runs')->where('project_id', $projectId)->first();
        $this->assertNotNull($run);
        $response->assertRedirect(route('ai-agency.show', $run->id));
        $this->assertSame('draft', $run->status);
    }

    public function test_editor_cannot_access_ai_agency(): void
    {
        $editor = User::factory()->create();
        $this->role($editor, 'editor');

        $this->actingAs($editor)->get(route('ai-agency.index'))->assertForbidden();
    }

    public function test_superadmin_can_store_the_openai_key_without_exposing_it(): void
    {
        $superadmin = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $key = 'sk-test-'.Str::random(40);

        $this->actingAs($superadmin)->put(route('ai-agency.configure'), ['api_key' => $key])
            ->assertSessionHasNoErrors();

        $stored = DB::table('app_settings')->where('key', 'ai_agency_openai')->value('value');
        $this->assertStringNotContainsString($key, $stored);
        $this->actingAs($superadmin)->get(route('ai-agency.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centro/AiAgency')
                ->where('configured', true)
                ->where('canConfigure', true)
            );
    }

    public function test_first_workflow_step_requires_brief_approval_before_unlocking_the_next_step(): void
    {
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $runId = (string) Str::uuid();
        $firstStepId = (string) Str::uuid();
        $secondStepId = (string) Str::uuid();

        DB::table('ai_agency_runs')->insert([
            'id' => $runId,
            'project_id' => $projectId,
            'created_by' => $admin->id,
            'status' => 'approved',
            'proposal' => json_encode(['missing_information' => ['Qual è l’obiettivo principale?']]),
            'project_snapshot' => json_encode(['files' => [['name' => 'brief.pdf', 'mime_type' => 'application/pdf']]]),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ([[$firstStepId, 'Raccolta informazioni cliente', 'todo', 0], [$secondStepId, 'Analisi Cliente', 'blocked', 1]] as [$id, $name, $status, $position]) {
            DB::table('ai_agency_steps')->insert([
                'id' => $id,
                'run_id' => $runId,
                'name' => $name,
                'status' => $status,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)->post(route('ai-agency.steps.start', [$runId, $firstStepId]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('ai-agency.steps.update', [$runId, $firstStepId]), [
            'answers' => ['0' => 'Generare richieste di preventivo.'],
            'file_assessments' => ['0' => 'relevant'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('ai-agency.steps.submit', [$runId, $firstStepId]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_agency_steps', ['id' => $firstStepId, 'status' => 'approval']);
        $this->assertDatabaseHas('ai_agency_steps', ['id' => $secondStepId, 'status' => 'blocked']);

        $this->actingAs($admin)->post(route('ai-agency.steps.approve', [$runId, $firstStepId]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_agency_steps', ['id' => $firstStepId, 'status' => 'completed']);
        $this->assertDatabaseHas('ai_agency_steps', ['id' => $secondStepId, 'status' => 'todo']);
        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'project_brief']);
    }

    public function test_pm_strategy_runs_autonomously_and_stops_for_strategy_approval(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key', 'ai-agency.model' => 'test-model']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $runId = (string) Str::uuid();
        $document = [
            'summary' => 'Sintesi',
            'findings' => ['Evidenza'],
            'recommendations' => ['Raccomandazione'],
            'risks' => [],
            'assumptions' => [],
            'sources' => [],
        ];
        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => json_encode([
                    'client_analysis' => $document,
                    'competitor_analysis' => $document,
                    'strategy' => $document,
                ])]],
            ]],
            'usage' => ['input_tokens' => 800, 'output_tokens' => 400],
        ])]);

        DB::table('ai_agency_runs')->insert([
            'id' => $runId,
            'project_id' => $projectId,
            'created_by' => $admin->id,
            'status' => 'approved',
            'proposal' => json_encode([]),
            'project_snapshot' => json_encode([]),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['Raccolta informazioni cliente', 'Analisi Cliente', 'Analisi Competitor', 'Definizione Strategia', 'Creazione Sitemap'] as $position => $name) {
            DB::table('ai_agency_steps')->insert([
                'id' => (string) Str::uuid(),
                'run_id' => $runId,
                'name' => $name,
                'status' => $position === 0 ? 'completed' : ($position === 1 ? 'todo' : 'blocked'),
                'position' => $position,
                'output_data' => $position === 0 ? json_encode(['files' => [], 'questions' => []]) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)->post(route('ai-agency.pm-strategy.execute', $runId))->assertSessionHasNoErrors();

        $this->assertSame(
            ['completed', 'completed', 'completed', 'approval', 'blocked'],
            DB::table('ai_agency_steps')->where('run_id', $runId)->orderBy('position')->pluck('status')->all(),
        );
        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'client_analysis']);
        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'competitor_analysis']);
        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'strategy']);

        $this->actingAs($admin)->post(route('ai-agency.pm-strategy.approve', $runId))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_agency_steps', ['run_id' => $runId, 'position' => 4, 'status' => 'todo']);
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    private function project(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('projects')->insert([
            'id' => $id,
            'name' => 'Nuovo sito LU3G',
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
