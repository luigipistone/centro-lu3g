<?php

namespace Tests\Feature;

use App\Models\ApiConnection;
use App\Models\User;
use App\Services\IntegrationWebhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private function setupConnection(array $scopes = ['clients:read', 'projects:read', 'tasks:read', 'tasks:write', 'comments:write', 'webhooks:write']): array
    {
        $u = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'role' => 'superadmin']);
        $client = (string) Str::uuid();
        $project = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $client, 'name' => 'Cliente API', 'active' => true]);
        DB::table('projects')->insert(['id' => $project, 'client_id' => $client, 'name' => 'Web API', 'status' => 'active']);
        $c = ApiConnection::create(['name' => 'Test Notes', 'site' => 'https://example.com', 'client_id' => $client, 'project_id' => $project, 'created_by' => $u->id, 'scopes' => $scopes, 'expires_at' => now()->addMonth()]);
        $token = $c->createToken('test', $scopes, $c->expires_at)->plainTextToken;
        $this->withToken($token);

        return [$c, $u, $token];
    }

    private function note(string $ref = 'site-task'): array
    {
        return ['title' => 'Revisione sito', 'description' => 'Contesto <script>alert(1)</script>', 'external_ref' => $ref];
    }

    public function test_task_note_comment_and_idempotency_with_scope_isolation(): void
    {
        [$c] = $this->setupConnection();
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(1, 'data');
        $task = $this->postJson('/api/v1/tasks', $this->note())->assertCreated()->json('id');
        $this->postJson('/api/v1/tasks', $this->note())->assertOk()->assertJsonPath('id', $task);
        $this->postJson('/api/v1/tasks', [...$this->note(), 'title' => 'Changed'])->assertStatus(409);
        $sub = $this->postJson("/api/v1/tasks/$task/subtasks", $this->note('note-1'))->assertCreated()->json('id');
        $this->patchJson("/api/v1/subtasks/$sub", ['status' => 'done', 'version' => 1])->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('version', 2);
        $this->patchJson("/api/v1/subtasks/$sub", ['status' => 'open', 'version' => 1])->assertStatus(409);
        $this->assertDatabaseHas('tasks', ['id' => $sub, 'status' => 'done', 'project_id' => $c->project_id]);
        $this->assertStringNotContainsString('<script>', DB::table('tasks')->where('id', $sub)->value('description'));
        $comment = ['body' => 'Risposta', 'author_name' => 'Mario', 'external_ref' => 'reply-1'];
        $this->postJson("/api/v1/subtasks/$sub/comments", $comment)->assertCreated();
        $this->postJson("/api/v1/subtasks/$sub/comments", $comment)->assertOk();
        $this->assertDatabaseCount('task_comments', 1);
        $this->getJson("/api/v1/tasks/$task/subtasks?per_page=1")->assertOk()->assertJsonPath('meta.total', 1);
        $this->setupConnection();
        $this->getJson("/api/v1/tasks/$task")->assertNotFound();
        $this->patchJson("/api/v1/subtasks/$sub", ['status' => 'open'])->assertNotFound();
    }

    public function test_tokens_scopes_expiry_revocation_and_errors(): void
    {
        [$c] = $this->setupConnection(['clients:read']);
        $this->postJson('/api/v1/tasks', $this->note())->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->getJson('/api/v1/clients?per_page=101')->assertUnprocessable()->assertJsonPath('error.code', 'validation_error');
        $c->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/me')->assertUnauthorized();
        [$c] = $this->setupConnection();
        $this->postJson('/api/v1/connect/revoke')->assertOk();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_management_is_superadmin_only_and_token_not_returned_again(): void
    {
        [$c, $u] = $this->setupConnection();
        $this->actingAs($u)->getJson('/settings/api-connections')->assertOk()->assertJsonMissingPath('token');
        $this->postJson('/settings/api-connections', ['name' => 'Sito', 'site' => 'https://example.org', 'client_id' => $c->client_id, 'project_id' => $c->project_id, 'scopes' => ['tasks:read'], 'expires_at' => now()->addMonth()->toDateString()])->assertCreated()->assertJsonStructure(['token']);
        $this->getJson('/settings/api-connections')->assertOk()->assertJsonMissingPath('connections.0.token');
        $this->actingAs(User::factory()->create())->getJson('/settings/api-connections')->assertForbidden();
    }

    public function test_webhook_signatures_retries_and_local_changes(): void
    {
        Http::fake(['*' => Http::sequence()->push('', 500)->push('', 200)]);
        [$c] = $this->setupConnection();
        $task = $this->postJson('/api/v1/tasks', $this->note())->assertCreated()->json('id');
        $sub = $this->postJson("/api/v1/tasks/$task/subtasks", $this->note('note-1'))->assertCreated()->json('id');
        $hook = (string) Str::uuid();
        DB::table('api_webhooks')->insert(['id' => $hook, 'connection_id' => $c->id, 'task_id' => $task, 'url' => 'https://example.com/hook', 'secret' => Crypt::encryptString('secret'), 'events' => json_encode(['subtask.updated', 'comment.created']), 'created_at' => now(), 'updated_at' => now()]);
        $service = \Mockery::mock(IntegrationWebhooks::class)->makePartial();
        $service->shouldReceive('validateUrl')->andReturn(['example.com', '93.184.216.34']);
        $service->capture();
        $service->deliver();
        $this->assertDatabaseHas('api_webhook_deliveries', ['status' => 'pending', 'attempts' => 1, 'http_status' => 500]);
        $this->travel(2)->minutes();
        $service->deliver();
        $this->assertDatabaseHas('api_webhook_deliveries', ['status' => 'delivered', 'attempts' => 2]);
        Http::assertSent(fn ($r) => $r->header('X-Centro-Signature')[0] === 'sha256='.hash_hmac('sha256', $r->header('X-Centro-Timestamp')[0].'.'.$r->body(), 'secret'));
        DB::table('tasks')->where('id', $sub)->update(['status' => 'done', 'updated_at' => now()]);
        $service->capture();
        $this->assertDatabaseCount('api_webhook_deliveries', 2);
        $service->capture();
        $this->assertDatabaseCount('api_webhook_deliveries', 2);
    }

    public function test_webhook_rejects_internal_or_foreign_destinations(): void
    {
        $s = new IntegrationWebhooks;
        foreach (['http://example.com/hook', 'https://127.0.0.1/hook', 'https://other.example/hook', 'https://user@example.com/hook', 'https://example.com:444/hook'] as $url) {
            try {
                $s->validateUrl($url, 'https://example.com');
                $this->fail('URL accepted');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_documentation_and_validation_are_available_in_settings(): void
    {
        [, $u] = $this->setupConnection();
        $this->actingAs($u)->postJson('/settings/api-connections', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->getJson('/settings/api-documentation')->assertOk()->assertJsonStructure(['markdown', 'html']);
        $this->get('/settings/api-openapi')->assertOk()->assertHeader('Content-Type', 'application/json');
        $spec = json_decode(file_get_contents(base_path('docs/integration-openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(11, $spec['paths']);
        $this->assertSame('3.0.3', $spec['openapi']);
    }

    public function test_reordered_payloads_are_idempotent_and_context_survives_editing(): void
    {
        [$c] = $this->setupConnection();
        $p = [...$this->note(), 'meta' => ['selector' => '#hero', 'viewport' => 'desktop'], 'external_url' => 'https://example.com/page'];
        $task = $this->postJson('/api/v1/tasks', $p)->assertCreated()->json('id');
        $p['meta'] = array_reverse($p['meta'], true);
        $this->postJson('/api/v1/tasks', array_reverse($p, true))->assertOk()->assertJsonPath('id', $task);
        $sub = $this->postJson("/api/v1/tasks/$task/subtasks", $p)->assertCreated()->json('id');
        $this->patchJson("/api/v1/subtasks/$sub", ['description' => 'Nuovo testo'])->assertOk();
        $this->assertStringContainsString('https://example.com/page', DB::table('tasks')->where('id', $sub)->value('description'));
        $this->assertDatabaseHas('task_assignees', ['task_id' => $sub, 'user_id' => $c->created_by]);
        $this->postJson('/api/v1/tasks', [...$this->note('other'), 'project_id' => (string) Str::uuid()])->assertForbidden();
        $this->getJson("/api/v1/tasks/$task/subtasks?updated_since=invalid")->assertUnprocessable();
        DB::table('tasks')->where('id', $task)->update(['client_id' => null]);
        $this->patchJson("/api/v1/subtasks/$sub", ['status' => 'done'])->assertNotFound();
    }

    public function test_permission_changes_and_disabled_owner_take_effect_immediately(): void
    {
        [$c, $u] = $this->setupConnection();
        $this->actingAs($u)->patchJson('/settings/api-connections/'.$c->id, ['scopes' => ['clients:read']])->assertOk();
        $this->postJson('/api/v1/tasks', $this->note())->assertForbidden();
        $this->getJson('/api/v1/clients')->assertOk();
        DB::table('user_roles')->where('user_id', $u->id)->delete();
        $this->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_revocation_cancels_pending_webhooks_without_sending(): void
    {
        Http::fake();
        [$c] = $this->setupConnection();
        $task = $this->postJson('/api/v1/tasks', $this->note())->assertCreated()->json('id');
        $this->postJson("/api/v1/tasks/$task/subtasks", $this->note('note-1'))->assertCreated();
        DB::table('api_webhooks')->insert(['id' => (string) Str::uuid(), 'connection_id' => $c->id, 'task_id' => $task, 'url' => 'https://example.com/hook', 'secret' => Crypt::encryptString('secret'), 'events' => json_encode(['subtask.updated']), 'created_at' => now(), 'updated_at' => now()]);
        app(IntegrationWebhooks::class)->capture();
        $this->postJson('/api/v1/connect/revoke')->assertOk();
        app(IntegrationWebhooks::class)->deliver();
        Http::assertNothingSent();
        $this->assertDatabaseHas('api_webhook_deliveries', ['status' => 'cancelled', 'attempts' => 0]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_completion_respects_dependencies_and_manual_descendants(): void
    {
        $this->setupConnection();
        $task = $this->postJson('/api/v1/tasks', $this->note())->assertCreated()->json('id');
        $sub = $this->postJson("/api/v1/tasks/$task/subtasks", $this->note('note-1'))->assertCreated()->json('id');
        DB::table('task_dependencies')->insert(['id' => (string) Str::uuid(), 'task_id' => $sub, 'depends_on_task_id' => $task]);
        $this->patchJson("/api/v1/subtasks/$sub", ['status' => 'done'])->assertStatus(409);
        DB::table('task_dependencies')->where('task_id', $sub)->delete();
        DB::table('tasks')->insert(['id' => (string) Str::uuid(), 'title' => 'Manuale', 'parent_task_id' => $sub, 'status' => 'todo']);
        $this->patchJson("/api/v1/subtasks/$sub", ['status' => 'done'])->assertStatus(409);
        $this->assertDatabaseHas('tasks', ['id' => $sub, 'status' => 'todo']);
    }

    public function test_local_comments_are_delivered_once_and_imported_comments_do_not_echo(): void
    {
        [$c, $u] = $this->setupConnection();
        $task = $this->postJson('/api/v1/tasks', $this->note())->assertCreated()->json('id');
        $sub = $this->postJson("/api/v1/tasks/$task/subtasks", $this->note('note-1'))->assertCreated()->json('id');
        DB::table('api_webhooks')->insert(['id' => (string) Str::uuid(), 'connection_id' => $c->id, 'task_id' => $task, 'url' => 'https://example.com/hook', 'secret' => Crypt::encryptString('secret'), 'events' => json_encode(['comment.created']), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson("/api/v1/subtasks/$sub/comments", ['body' => 'Dal sito', 'external_ref' => 'reply-1'])->assertCreated();
        DB::table('task_comments')->insert(['id' => (string) Str::uuid(), 'task_id' => $sub, 'user_id' => $u->id, 'content' => '<p>Dal Centro</p>', 'created_at' => now(), 'updated_at' => now()]);
        app(IntegrationWebhooks::class)->capture();
        app(IntegrationWebhooks::class)->capture();
        $this->assertDatabaseCount('api_webhook_deliveries', 1);
        $payload = json_decode(DB::table('api_webhook_deliveries')->value('payload'), true);
        $this->assertSame('<p>Dal Centro</p>', $payload['data']['body']);
        Http::fake();
        DB::table('tasks')->where('id', $sub)->update(['client_id' => null]);
        app(IntegrationWebhooks::class)->deliver();
        Http::assertNothingSent();
        $this->assertDatabaseHas('api_webhook_deliveries', ['status' => 'cancelled']);
    }
}
