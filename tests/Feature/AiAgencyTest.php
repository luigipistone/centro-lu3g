<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
