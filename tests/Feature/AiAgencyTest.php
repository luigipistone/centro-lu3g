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

    public function test_admin_can_create_a_process_and_editor_cannot_access_it(): void
    {
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $this->role($admin, 'admin');
        $this->role($editor, 'editor');
        $projectId = $this->project($admin);

        $response = $this->actingAs($admin)->post(route('ai-agency.store'), ['project_id' => $projectId]);
        $run = DB::table('ai_agency_runs')->where('project_id', $projectId)->first();
        $response->assertRedirect(route('ai-agency.show', $run->id));
        $this->assertSame('draft', $run->status);
        $this->actingAs($editor)->get(route('ai-agency.index'))->assertForbidden();
    }

    public function test_superadmin_can_store_the_openai_key_encrypted(): void
    {
        $superadmin = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $key = 'sk-test-'.Str::random(40);
        $this->actingAs($superadmin)->put(route('ai-agency.configure'), ['api_key' => $key])->assertSessionHasNoErrors();
        $stored = DB::table('app_settings')->where('key', 'ai_agency_openai')->value('value');
        $this->assertStringNotContainsString($key, $stored);
        $this->actingAs($superadmin)->get(route('ai-agency.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Centro/AiAgency')->where('configured', true)->where('canConfigure', true));
    }

    public function test_complete_analysis_precedes_service_approval(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $serviceId = $this->service();
        $runId = $this->createRun($admin, $projectId);
        Http::fake(['api.openai.com/*' => Http::response($this->apiResponse($this->analysis(true, $serviceId)))]);

        $this->actingAs($admin)->post(route('ai-agency.analyze', $runId))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_agency_runs', ['id' => $runId, 'status' => 'proposal_ready']);
        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'client_analysis']);
        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'competitor_analysis']);
        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'strategy']);

        $this->actingAs($admin)->post(route('ai-agency.approve', $runId), ['service_ids' => [$serviceId]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_agency_runs', ['id' => $runId, 'status' => 'approved']);
    }

    public function test_only_blocking_information_interrupts_analysis_and_then_it_resumes(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $serviceId = $this->service();
        $runId = $this->createRun($admin, $projectId);
        $blocked = $this->analysis(false, $serviceId);
        $blocked['readiness']['blocking_questions'] = ['Qual è l’azienda corretta?'];
        Http::fakeSequence('api.openai.com/*')
            ->push($this->apiResponse($blocked))
            ->push($this->apiResponse($this->analysis(true, $serviceId)));

        $this->actingAs($admin)->post(route('ai-agency.analyze', $runId))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_agency_runs', ['id' => $runId, 'status' => 'needs_information']);

        $this->actingAs($admin)->post(route('ai-agency.information.store', $runId), [
            'answers' => ['Pedrinazzi SRL'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_agency_artifacts', ['run_id' => $runId, 'type' => 'project_brief']);
        $this->assertDatabaseHas('ai_agency_runs', ['id' => $runId, 'status' => 'proposal_ready']);
    }

    public function test_analysis_repairs_literal_control_characters_in_structured_output(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $serviceId = $this->service();
        $runId = $this->createRun($admin, $projectId);
        $response = $this->apiResponse($this->analysis(true, $serviceId));
        $response['output'][0]['content'][0]['text'] = str_replace('Analisi completa', "Analisi\vcompleta", $response['output'][0]['content'][0]['text']);
        Http::fake(['api.openai.com/*' => Http::response($response)]);

        $this->actingAs($admin)->post(route('ai-agency.analyze', $runId))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_agency_runs', ['id' => $runId, 'status' => 'proposal_ready', 'pending_output' => null]);
    }

    public function test_analysis_resumes_from_saved_output_without_another_api_call(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $serviceId = $this->service();
        $runId = $this->createRun($admin, $projectId);
        DB::table('ai_agency_runs')->where('id', $runId)->update([
            'status' => 'error',
            'pending_output' => json_encode($this->analysis(true, $serviceId)),
            'pending_usage' => json_encode(['input_tokens' => 700, 'output_tokens' => 300, 'web_searches' => 1]),
        ]);
        Http::preventStrayRequests();

        $this->actingAs($admin)->post(route('ai-agency.analyze', $runId))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_agency_runs', ['id' => $runId, 'status' => 'proposal_ready', 'input_tokens' => 700, 'output_tokens' => 300, 'web_searches' => 1]);
    }

    public function test_analysis_recovers_complete_sections_from_a_truncated_saved_response(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $serviceId = $this->service();
        $runId = $this->createRun($admin, $projectId);
        $analysis = $this->analysis(true, $serviceId);
        $analysis['strategy']['recommendations'] = ['Definire la struttura', 'Realizzare il sito'];
        $output = json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $output = substr($output, 0, strpos($output, '"priorities"') + 30);
        DB::table('ai_agency_runs')->where('id', $runId)->update([
            'status' => 'error',
            'pending_output' => $output,
            'pending_usage' => json_encode(['input_tokens' => 900, 'output_tokens' => 450, 'web_searches' => 1]),
        ]);
        Http::preventStrayRequests();

        $this->actingAs($admin)->post(route('ai-agency.analyze', $runId))->assertSessionHasNoErrors();

        $run = DB::table('ai_agency_runs')->where('id', $runId)->first();
        $proposal = json_decode($run->proposal, true);
        $this->assertSame('proposal_ready', $run->status);
        $this->assertSame(['Definire la struttura', 'Realizzare il sito'], $proposal['roadmap']);
        $this->assertStringContainsString('WEB', $proposal['priorities'][0]);
    }

    public function test_operational_engine_completes_any_module_and_unlocks_the_next_one(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $runId = $this->createRun($admin, $projectId);
        $serviceId = $this->service();
        DB::table('ai_agency_runs')->where('id', $runId)->update(['status' => 'approved', 'proposal' => json_encode($this->analysis(true, $serviceId))]);
        [$firstStep, $secondStep] = $this->operationalSteps($runId, $admin, true, $serviceId);
        Http::fake(['api.openai.com/*' => Http::response($this->textResponse("# Sitemap\n\n- Home\n- Servizi\n- Contatti"))]);

        $this->actingAs($admin)->postJson(route('ai-agency.execute-next', $runId))->assertOk()->assertJson(['state' => 'completed_step', 'continue' => true]);

        $this->assertDatabaseHas('ai_agency_steps', ['id' => $firstStep, 'status' => 'completed']);
        $this->assertDatabaseHas('ai_agency_steps', ['id' => $secondStep, 'status' => 'todo']);
        $this->assertStringContainsString('Sitemap', DB::table('ai_agency_steps')->where('id', $firstStep)->value('output_data'));
    }

    public function test_operational_engine_only_stops_for_blocking_information_and_can_resume(): void
    {
        config(['ai-agency.api_key' => 'sk-test-key']);
        $admin = User::factory()->create();
        $this->role($admin, 'admin');
        $projectId = $this->project($admin);
        $runId = $this->createRun($admin, $projectId);
        DB::table('ai_agency_runs')->where('id', $runId)->update(['status' => 'approved']);
        [$stepId] = $this->operationalSteps($runId, $admin, false);
        Http::fake(['api.openai.com/*' => Http::response($this->textResponse("BLOCCATO\nQual è il dominio definitivo?"))]);

        $this->actingAs($admin)->postJson(route('ai-agency.execute-next', $runId))->assertOk()->assertJson(['state' => 'needs_information', 'continue' => false]);
        $this->assertDatabaseHas('ai_agency_steps', ['id' => $stepId, 'status' => 'needs_information']);

        $this->actingAs($admin)->postJson(route('ai-agency.steps.information', [$runId, $stepId]), ['answers' => ['pedrinazzi.it']])->assertOk();
        $this->assertDatabaseHas('ai_agency_steps', ['id' => $stepId, 'status' => 'todo']);
        $this->assertStringContainsString('pedrinazzi.it', DB::table('ai_agency_steps')->where('id', $stepId)->value('input_data'));
    }

    private function analysis(bool $ready, string $serviceId): array
    {
        $document = ['summary' => $ready ? 'Analisi completa' : '', 'findings' => [], 'recommendations' => [], 'risks' => [], 'assumptions' => [], 'sources' => []];
        return [
            'readiness' => ['ready' => $ready, 'summary' => $ready ? 'Fonti sufficienti' : 'Serve un chiarimento', 'blocking_questions' => [], 'conflicts' => [], 'document_assessments' => []],
            'client_analysis' => $document,
            'competitor_analysis' => $document,
            'strategy' => $document,
            'recommended_services' => $ready ? [['service_id' => $serviceId, 'name' => 'WEB', 'motivation' => 'Necessario', 'confidence' => 95]] : [],
            'not_recommended_services' => [],
            'priorities' => $ready ? ['Sito web'] : [],
            'roadmap' => $ready ? ['Analisi', 'Realizzazione'] : [],
        ];
    }

    private function apiResponse(array $analysis): array
    {
        return ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($analysis)]]]], 'usage' => ['input_tokens' => 700, 'output_tokens' => 300]];
    }

    private function textResponse(string $text): array
    {
        return ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]]], 'usage' => ['input_tokens' => 500, 'output_tokens' => 120]];
    }

    private function operationalSteps(string $runId, User $user, bool $withSecond = true, ?string $serviceId = null): array
    {
        $folderId = (string) Str::uuid();
        DB::table('admin_module_folders')->insert(['id' => $folderId, 'name' => 'Workflow', 'color' => '#2563eb', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $moduleIds = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ($moduleIds as $index => $moduleId) {
            DB::table('admin_modules')->insert(['id' => $moduleId, 'admin_module_folder_id' => $folderId, 'name' => $index === 0 ? 'Creazione Sitemap' : 'Struttura SEO', 'version' => '1.0', 'status' => 'approved', 'rules' => 'Produci il risultato usando i dati del progetto.', 'active' => true, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $serviceId ??= $this->service();
        $stepIds = [(string) Str::uuid(), (string) Str::uuid()];
        DB::table('ai_agency_steps')->insert(['id' => $stepIds[0], 'run_id' => $runId, 'service_id' => $serviceId, 'module_id' => $moduleIds[0], 'name' => 'Creazione Sitemap', 'status' => 'todo', 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
        if ($withSecond) DB::table('ai_agency_steps')->insert(['id' => $stepIds[1], 'run_id' => $runId, 'service_id' => $serviceId, 'module_id' => $moduleIds[1], 'name' => 'Struttura SEO', 'status' => 'blocked', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        return $withSecond ? $stepIds : [$stepIds[0]];
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
    }

    private function project(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('projects')->insert(['id' => $id, 'name' => 'Nuovo sito', 'status' => 'active', 'color' => '#2563eb', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function service(): string
    {
        $id = (string) Str::uuid();
        DB::table('services')->insert(['id' => $id, 'name' => 'WEB', 'description' => 'Siti web', 'color' => '#2563eb', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function createRun(User $user, string $projectId): string
    {
        $id = (string) Str::uuid();
        DB::table('ai_agency_runs')->insert(['id' => $id, 'project_id' => $projectId, 'created_by' => $user->id, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }
}
