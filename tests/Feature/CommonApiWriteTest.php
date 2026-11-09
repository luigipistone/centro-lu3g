<?php

namespace Tests\Feature;

use App\Http\Controllers\CentroPageController;
use App\Models\ApiConnection;
use App\Models\User;
use App\Services\CommonApiAccess;
use App\Services\CommonApiWebhooks;
use App\Services\CommonApiWrites;
use App\Services\IntegrationWebhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommonApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private function connect(): ApiConnection
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'superadmin']);
        $c = ApiConnection::create(['name' => 'Writer', 'kind' => 'general', 'site' => 'https://example.com', 'created_by' => $user->id, 'scopes' => CommonApiAccess::SCOPES, 'access_policy' => array_fill_keys(['clients', 'projects', 'users'], ['all' => true, 'ids' => []]), 'expires_at' => now()->addMonth()]);
        $this->withToken($c->createToken('test', $c->scopes, $c->expires_at)->plainTextToken);

        return $c;
    }

    private function create(string $resource, array $body, string $key): TestResponse
    {
        return $this->postJson('/api/v1/'.$resource, $body, ['Idempotency-Key' => $key]);
    }

    public function test_client_project_task_comment_and_retry(): void
    {
        $c = $this->connect();
        $client = $this->create('clients', ['name' => 'API Client'], 'client')->assertCreated()->json('data.id');
        $this->create('clients', ['name' => 'API Client'], 'client')->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('data.id', $client);
        $this->create('clients', ['name' => 'Different'], 'client')->assertStatus(409);
        $project = $this->create('projects', ['name' => 'API Project', 'client_id' => $client], 'project')->assertCreated()->json('data.id');
        $task = $this->create('tasks', ['title' => 'API Task', 'project_id' => $project, 'assignee_ids' => [$c->created_by], 'description' => '<script>no</script>'], 'task')->assertCreated()->json('data.id');
        $this->assertStringNotContainsString('<script>', DB::table('tasks')->where('id', $task)->value('description'));
        $comment = $this->create("tasks/$task/comments", ['body' => 'Hello'], 'comment')->assertCreated()->json('data.id');
        $this->create("tasks/$task/comments", ['body' => 'Hello'], 'comment')->assertOk()->assertJsonPath('data.id', $comment);
        $this->assertDatabaseCount('task_comments', 1);
        $this->assertDatabaseCount('api_write_keys', 4);
        $this->getJson('/api/v1/comments?task_id='.$task)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_patch_requires_current_etag_and_preserves_missing_fields(): void
    {
        $this->connect();
        $response = $this->create('clients', ['name' => 'Before', 'phone' => '123'], 'client')->assertCreated();
        $id = $response->json('data.id');
        $tag = $response->headers->get('ETag');
        $this->patchJson('/api/v1/clients/'.$id, ['name' => 'After'])->assertStatus(428);
        $this->patchJson('/api/v1/clients/'.$id, ['name' => 'After'], ['If-Match' => $tag])->assertOk()->assertJsonPath('data.phone', '123');
        $this->patchJson('/api/v1/clients/'.$id, ['name' => 'Stale'], ['If-Match' => $tag])->assertStatus(412);
        $tag = $this->getJson('/api/v1/clients/'.$id)->headers->get('ETag');
        $this->patchJson('/api/v1/clients/'.$id, ['active' => false], ['If-Match' => $tag])->assertOk()->assertJsonPath('data.active', false);
    }

    public function test_scope_unknown_fields_and_failed_requests_do_not_write(): void
    {
        $c = $this->connect();
        $this->create('clients', ['name' => 'No', 'created_by' => $c->created_by], 'bad')->assertUnprocessable();
        $this->create('tasks', ['title' => 'No project'], 'bad')->assertUnprocessable();
        $this->assertDatabaseCount('api_write_keys', 0);
        $c->update(['scopes' => ['clients:read']]);
        $this->create('clients', ['name' => 'No'], 'no')->assertForbidden();
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_selected_policy_gains_only_created_resource(): void
    {
        $c = $this->connect();
        $c->update(['access_policy' => array_fill_keys(['clients', 'projects', 'users'], ['all' => false, 'ids' => []])]);
        $id = $this->create('clients', ['name' => 'Created'], 'new')->assertCreated()->json('data.id');
        $this->assertSame([$id], $c->fresh()->access_policy['clients']['ids']);
        $this->getJson('/api/v1/clients/'.$id)->assertOk();
    }

    private function patchRecord(string $resource, string $id, array $body): TestResponse
    {
        $tag = $this->getJson('/api/v1/'.$resource.'/'.$id)->assertOk()->headers->get('ETag');

        return $this->patchJson('/api/v1/'.$resource.'/'.$id, $body, ['If-Match' => $tag]);
    }

    public function test_task_patch_preserves_dates_people_and_recurrence(): void
    {
        $c = $this->connect();
        $observer = User::factory()->create();
        $project = $this->create('projects', ['name' => 'Project', 'user_ids' => [$observer->id]], 'p')->assertCreated()->json('data.id');
        $this->patchRecord('projects', $project, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.user_ids', [$observer->id]);
        $task = $this->create('tasks', ['title' => 'Recurring', 'task_type' => 'ongoing', 'project_id' => $project, 'assignee_ids' => [$c->created_by], 'follower_ids' => [$observer->id], 'start_date' => '2026-11-09', 'due_date' => '2026-11-13', 'due_time' => '16:30', 'recurring_enabled' => true, 'recurring_interval_value' => 2, 'recurring_interval_unit' => 'week', 'recurring_mode' => 'fixed'], 't')->assertCreated()->json('data.id');
        $this->patchRecord('tasks', $task, ['title' => 'Renamed', 'follower_ids' => []])->assertOk()->assertJsonPath('data.assignee_ids', [$c->created_by])->assertJsonPath('data.follower_ids', [])->assertJsonPath('data.start_date', '2026-11-09')->assertJsonPath('data.due_date', '2026-11-13');
        $this->assertDatabaseHas('tasks', ['id' => $task, 'recurring_enabled' => true, 'recurring_interval_value' => 2]);
        $this->patchRecord('tasks', $task, ['due_time' => '16:30INVALID'])->assertUnprocessable();
        $this->patchRecord('tasks', $task, ['due_date' => '2026-11-01'])->assertUnprocessable();
    }

    public function test_subtasks_and_project_client_changes_are_guarded(): void
    {
        $this->connect();
        $client = $this->create('clients', ['name' => 'Client'], 'c')->assertCreated()->json('data.id');
        $project = $this->create('projects', ['name' => 'Project', 'client_id' => $client], 'p')->assertCreated()->json('data.id');
        $task = $this->create('tasks', ['title' => 'Parent', 'project_id' => $project], 't')->assertCreated()->json('data.id');
        $child = $this->create('tasks', ['title' => 'Child', 'parent_task_id' => $task], 'child')->assertCreated()->assertJsonPath('data.project_id', $project)->assertJsonPath('data.client_id', $client)->json('data.id');
        $this->getJson('/api/v1/tasks?parent_task_id='.$task)->assertOk()->assertJsonCount(1, 'data');
        $this->patchRecord('tasks', $task, ['status' => 'done'])->assertStatus(409);
        $this->patchRecord('projects', $project, ['client_id' => null])->assertStatus(409);
        $this->patchRecord('tasks', $child, ['parent_task_id' => null])->assertUnprocessable();
        $this->patchRecord('tasks', $child, ['status' => 'done'])->assertOk();
        $this->patchRecord('tasks', $task, ['status' => 'done'])->assertOk();
        $this->patchRecord('projects', $project, ['status' => 'archived'])->assertOk();
        $this->create('tasks', ['title' => 'Blocked', 'project_id' => $project], 'archived')->assertStatus(409);
    }

    public function test_cross_scope_relations_and_comments_are_denied(): void
    {
        $c = $this->connect();
        $project = $this->create('projects', ['name' => 'Project'], 'p')->assertCreated()->json('data.id');
        $task = $this->create('tasks', ['title' => 'Task', 'project_id' => $project], 't')->assertCreated()->json('data.id');
        $comment = $this->create("tasks/$task/comments", ['body' => 'Private'], 'comment')->assertCreated()->json('data.id');
        $policy = $c->access_policy;
        $policy['users'] = ['all' => false, 'ids' => []];
        $c->update(['access_policy' => $policy]);
        $this->patchRecord('tasks', $task, ['assignee_ids' => [$c->created_by]])->assertForbidden();
        $policy['projects'] = ['all' => false, 'ids' => []];
        $c->update(['access_policy' => $policy]);
        $this->getJson('/api/v1/comments/'.$comment)->assertNotFound();
        $this->getJson('/api/v1/comments')->assertOk()->assertJsonCount(0, 'data');
        $this->create("tasks/$task/comments", ['body' => 'No'], 'denied')->assertNotFound();
        $this->create('tasks', ['title' => 'No', 'project_id' => $project], 'denied')->assertForbidden();
        $this->assertDatabaseCount('task_comments', 1);
    }

    public function test_failed_request_key_can_be_reused_and_spec_documents_writes(): void
    {
        $this->connect();
        $this->create('clients', ['name' => 'Bad', 'email' => 'not-an-email'], 'retry')->assertUnprocessable();
        $this->create('clients', ['name' => 'Good', 'email' => 'test@example.com'], 'retry')->assertCreated();
        $this->assertDatabaseCount('clients', 1);
        $spec = $this->getJson('/developers/openapi.json')->assertOk()->json();
        foreach (['clients', 'projects', 'tasks'] as $resource) {
            $this->assertArrayHasKey('post', $spec['paths']['/'.$resource]);
            $this->assertArrayHasKey('patch', $spec['paths']['/'.$resource.'/{id}']);
            $this->assertEqualsCanonicalizing(CommonApiWrites::FIELDS[$resource], array_keys($spec['components']['schemas'][$resource.'Patch']['properties']));
        }
        $this->assertArrayHasKey('post', $spec['paths']['/tasks/{id}/comments']);
        $this->get('/developers/writes')->assertOk()->assertSee('If-Match')->assertSee('Idempotency-Key');
    }

    public function test_comment_retries_produce_only_one_webhook_and_people_changes_are_detected(): void
    {
        $c = $this->connect();
        $project = $this->create('projects', ['name' => 'Project'], 'p')->assertCreated()->json('data.id');
        $task = $this->create('tasks', ['title' => 'Task', 'project_id' => $project], 't')->assertCreated()->json('data.id');
        $this->mock(IntegrationWebhooks::class, function ($mock) {
            $mock->shouldReceive('validateUrl')->andReturn(['example.com', '93.184.216.34']);
        });
        $this->postJson('/api/v1/webhooks', ['url' => 'https://example.com/events', 'events' => ['comments.created', 'tasks.updated']])->assertCreated();
        $this->create("tasks/$task/comments", ['body' => 'Hello'], 'comment')->assertCreated();
        $this->create("tasks/$task/comments", ['body' => 'Hello'], 'comment')->assertOk();
        DB::table('task_assignees')->insert(['id' => (string) Str::uuid(), 'task_id' => $task, 'user_id' => $c->created_by]);
        app(CommonApiWebhooks::class)->capture();
        app(CommonApiWebhooks::class)->capture();
        $this->assertDatabaseCount('api_events', 2);
        $this->assertDatabaseHas('api_events', ['event' => 'comments.created']);
        $this->assertDatabaseHas('api_events', ['event' => 'tasks.updated']);
    }

    public function test_exception_rolls_back_domain_write_and_idempotency_record(): void
    {
        $this->connect();
        $this->mock(CentroPageController::class, function ($mock) {
            $mock->shouldReceive('store')->once()->andReturnUsing(function () {
                DB::table('clients')->insert(['id' => (string) Str::uuid(), 'name' => 'Rollback']);
                throw new \RuntimeException('Simulated database failure');
            });
        });
        $this->create('clients', ['name' => 'Rollback'], 'rollback')->assertStatus(500);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('api_write_keys', 0);
    }

    public function test_open_dependency_prevents_completion_without_changing_version(): void
    {
        $this->connect();
        $project = $this->create('projects', ['name' => 'Project'], 'p')->assertCreated()->json('data.id');
        $task = $this->create('tasks', ['title' => 'Task', 'project_id' => $project], 't')->assertCreated()->json('data.id');
        $blocker = $this->create('tasks', ['title' => 'Blocker', 'project_id' => $project], 'b')->assertCreated()->json('data.id');
        DB::table('task_dependencies')->insert(['id' => (string) Str::uuid(), 'task_id' => $task, 'depends_on_task_id' => $blocker]);
        $tag = $this->getJson('/api/v1/tasks/'.$task)->headers->get('ETag');
        $this->patchJson('/api/v1/tasks/'.$task, ['status' => 'done'], ['If-Match' => $tag])->assertUnprocessable();
        $this->getJson('/api/v1/tasks/'.$task)->assertHeader('ETag', $tag)->assertJsonPath('data.status', 'todo');
    }
}
