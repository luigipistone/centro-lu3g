<?php

namespace Tests\Feature;

use App\Http\Controllers\CommonApiController;
use App\Http\Controllers\DeveloperPortalController;
use App\Models\ApiConnection;
use App\Models\User;
use App\Services\CommonApiAccess;
use App\Services\CommonApiWebhooks;
use App\Services\IntegrationWebhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommonApiTest extends TestCase
{
    use RefreshDatabase;

    private function connection(array $policy = []): ApiConnection
    {
        $u = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'role' => 'superadmin']);
        $c = ApiConnection::create(['name' => 'Common', 'kind' => 'general', 'site' => 'https://example.com', 'created_by' => $u->id,
            'scopes' => CommonApiAccess::SCOPES, 'access_policy' => $policy, 'expires_at' => now()->addMonth()]);
        $this->withToken($c->createToken('test', $c->scopes, $c->expires_at)->plainTextToken);

        return $c;
    }

    public function test_holidays_are_paginated_searchable_and_require_their_scope(): void
    {
        $id = (string) Str::uuid();
        DB::table('attendance_holidays')->insert(['id' => $id, 'name' => 'Natale', 'day' => '2025-12-25', 'end_day' => '2025-12-26', 'recurs_annually' => true]);
        $c = $this->connection();
        $this->getJson('/api/v1/holidays?per_page=1&search=Natale')->assertOk()->assertJsonPath('data.0.recurs_annually', true)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/holidays/'.$id)->assertOk()->assertJsonPath('data.day', '2025-12-25')->assertJsonPath('data.end_day', '2025-12-26');
        $this->getJson('/api/v1/holidays?per_page=101')->assertUnprocessable();
        $c->update(['scopes' => ['users:read']]);
        $this->getJson('/api/v1/holidays')->assertForbidden();
        $this->getJson('/api/v1/holidays/'.$id)->assertForbidden();
    }

    public function test_birth_date_is_only_exposed_for_authorized_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DB::table('profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'birth_date' => '1996-03-18', 'fiscal_code' => 'PRIVATE']);
        $this->connection(['users' => ['ids' => [$user->id]]]);
        $this->getJson('/api/v1/users')->assertOk()->assertJsonPath('data.0.birth_date', '1996-03-18')->assertJsonMissingPath('data.0.fiscal_code');
        $etag = $this->getJson('/api/v1/users/'.$user->id)->assertOk()->assertJsonPath('data.birth_date', '1996-03-18')->headers->get('ETag');
        $this->getJson('/api/v1/users/'.$other->id)->assertNotFound();
        DB::table('users')->where('id', $user->id)->update(['updated_at' => now()->subDays(2)]);
        DB::table('profiles')->where('user_id', $user->id)->update(['birth_date' => null, 'updated_at' => now()]);
        $response = $this->getJson('/api/v1/users/'.$user->id)->assertOk()->assertJsonPath('data.birth_date', null);
        $this->assertNotSame($etag, $response->headers->get('ETag'));
        $this->getJson('/api/v1/users?updated_since='.urlencode(now()->subDay()->toIso8601String()))->assertOk()->assertJsonPath('data.0.id', $user->id);
        $this->getJson('/developers/openapi.json')->assertOk()->assertJsonPath('components.schemas.users.properties.birth_date.format', 'date')->assertJsonPath('components.schemas.holidays.properties.recurs_annually.type', 'boolean');
        $this->get('/developers/holidays')->assertOk();
    }

    public function test_holiday_webhooks_do_not_require_a_person_or_client_policy(): void
    {
        $this->connection();
        $id = (string) Str::uuid();
        DB::table('attendance_holidays')->insert(['id' => $id, 'name' => 'Natale', 'day' => '2026-12-25']);
        $this->mock(IntegrationWebhooks::class, fn ($mock) => $mock->shouldReceive('validateUrl')->andReturn(['example.com', '93.184.216.34']));
        $this->postJson('/api/v1/webhooks', ['url' => 'https://example.com/holidays', 'events' => ['holidays.updated', 'holidays.removed']])->assertCreated();
        DB::table('attendance_holidays')->where('id', $id)->update(['name' => 'Chiusura Natale']);
        app(CommonApiWebhooks::class)->capture();
        $this->assertDatabaseCount('api_events', 1);
        DB::table('attendance_holidays')->where('id', $id)->delete();
        app(CommonApiWebhooks::class)->capture();
        $this->assertDatabaseCount('api_events', 2);
    }

    public function test_scoped_clients_and_search_across_pages(): void
    {
        $ids = [];
        foreach (range(1, 3) as $n) {
            $ids[] = $id = (string) Str::uuid();
            DB::table('clients')->insert(['id' => $id, 'name' => "Cliente $n", 'notes' => 'Private notes']);
        }
        $this->connection(['clients' => ['all' => false, 'ids' => array_slice($ids, 0, 2)]]);
        $this->getJson('/api/v1/clients?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonMissingPath('data.0.notes');
        $this->getJson('/api/v1/clients?search=Cliente%202')->assertOk()->assertJsonPath('data.0.id', $ids[1]);
        $this->getJson('/api/v1/clients/'.$ids[2])->assertNotFound();
        $this->getJson('/api/v1/clients/'.$ids[0])->assertOk();
        $this->getJson('/api/v1/clients?per_page=101')->assertUnprocessable();
    }

    public function test_empty_policy_never_inherits_admin_access_and_legacy_writes_are_blocked(): void
    {
        $this->connection();
        $this->getJson('/api/v1/users')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/tasks/'.Str::uuid().'/subtasks', ['title' => 'No'])->assertForbidden();
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_all_read_resources_and_private_fields(): void
    {
        $this->connection(['clients' => ['all' => true], 'projects' => ['all' => true], 'users' => ['all' => true]]);
        foreach (array_keys(CommonApiController::FIELDS) as $resource) {
            $this->getJson('/api/v1/'.$resource)->assertOk();
        }
        $this->getJson('/api/v1/users')->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_general_connection_creation_is_explicit_and_admin_only(): void
    {
        $c = $this->connection();
        $admin = User::find($c->created_by);
        $payload = ['name' => 'Dashboard', 'kind' => 'general', 'site' => 'https://example.com', 'scopes' => ['clients:read'],
            'expires_at' => now()->addMonth()->toDateString(), 'access_policy' => array_fill_keys(['clients', 'projects', 'users'], ['all' => false, 'ids' => []])];
        $this->actingAs($admin)->postJson('/settings/api-connections', $payload)->assertCreated()->assertJsonPath('connection.kind', 'general');
        $this->postJson('/settings/api-connections', [...$payload, 'scopes' => ['users:write']])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/settings/api-connections', $payload)->assertForbidden();
    }

    public function test_general_site_is_optional_until_webhooks_are_enabled(): void
    {
        $c = $this->connection();
        $this->actingAs(User::find($c->created_by));
        $payload = ['name' => 'TV', 'kind' => 'general', 'scopes' => ['clients:read'],
            'expires_at' => now()->addMonth()->toDateString(), 'access_policy' => array_fill_keys(['clients', 'projects', 'users'], ['all' => false, 'ids' => []])];
        $created = $this->postJson('/settings/api-connections', $payload)->assertCreated()->assertJsonPath('connection.site', null);
        $id = $created->json('connection.id');
        $scopes = ['clients:read', 'webhooks:write'];
        $this->postJson('/settings/api-connections', [...$payload, 'scopes' => $scopes])->assertUnprocessable()->assertJsonValidationErrors('site');
        $this->patchJson('/settings/api-connections/'.$id, ['scopes' => $scopes])->assertUnprocessable()->assertJsonValidationErrors('site');
        $this->assertSame(['clients:read'], ApiConnection::find($id)->scopes);
        foreach (['http://example.com', 'https://127.0.0.1', 'https://user:secret@example.com', 'https://example.com:8080'] as $site) {
            $this->postJson('/settings/api-connections', [...$payload, 'site' => $site])->assertUnprocessable()->assertJsonValidationErrors('site');
        }
        $this->patchJson('/settings/api-connections/'.$id, ['scopes' => $scopes, 'site' => 'https://Example.com/hook'])->assertOk();
        $this->assertSame('https://example.com', ApiConnection::find($id)->site);
        $this->patchJson('/settings/api-connections/'.$id, ['scopes' => $scopes, 'site' => null])->assertUnprocessable();
        $this->patchJson('/settings/api-connections/'.$id, ['scopes' => ['clients:read'], 'site' => null])->assertOk();
        $this->assertNull(ApiConnection::find($id)->site);
    }

    public function test_connection_expiration_survives_permission_and_usage_updates(): void
    {
        $c = $this->connection();
        $expires = $c->expires_at->toDateTimeString();
        $this->travel(10)->minutes();
        $this->actingAs(User::find($c->created_by))->patchJson('/settings/api-connections/'.$c->id, ['scopes' => ['clients:read']])->assertOk();
        $c->refresh()->update(['last_used_at' => now()]);
        $this->assertSame($expires, $c->fresh()->expires_at->toDateTimeString());
        $this->assertTrue($c->fresh()->enabled());
    }

    public function test_user_avatars_require_scope_and_authorized_active_user(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = User::factory()->create(['account_status' => 'active']);
        DB::table('profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'avatar_url' => '/avatars/test.png']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX2kAAAAASUVORK5CYII=');
        \Illuminate\Support\Facades\Storage::disk('local')->put('avatars/test.png', $png);
        $url = '/api/v1/users/'.$user->id.'/avatar';
        $this->getJson($url)->assertUnauthorized();
        $c = $this->connection(['users' => ['all' => false, 'ids' => [$user->id]]]);
        $this->getJson('/api/v1/users')->assertJsonPath('data.0.avatar_url', url($url));
        $this->getJson('/api/v1/users/'.$user->id)->assertJsonPath('data.avatar_url', url($url));
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $c->update(['access_policy' => ['users' => ['all' => false, 'ids' => []]]]);
        $this->getJson($url)->assertNotFound();
        $c->update(['access_policy' => ['users' => ['all' => true]], 'scopes' => ['clients:read']]);
        $this->getJson($url)->assertForbidden();
        $c->update(['scopes' => CommonApiAccess::SCOPES]);
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'inactive']);
        $this->getJson($url)->assertNotFound();
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'active']);
        \Illuminate\Support\Facades\Storage::disk('local')->delete('avatars/test.png');
        $this->getJson($url)->assertNotFound();
        \Illuminate\Support\Facades\Storage::disk('public')->put('avatars/test.png', $png);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        DB::table('profiles')->where('user_id', $user->id)->update(['avatar_url' => null]);
        $this->getJson('/api/v1/users/'.$user->id)->assertJsonPath('data.avatar_url', null);
        $this->getJson($url)->assertNotFound();
        DB::table('profiles')->where('user_id', $user->id)->update(['avatar_url' => '/avatars/../secret']);
        $this->getJson($url)->assertNotFound();
    }

    public function test_documentation_is_public_and_contract_matches_resources(): void
    {
        foreach (array_keys(DeveloperPortalController::GUIDES + DeveloperPortalController::RESOURCES) as $page) {
            $this->get('/developers/'.$page)->assertOk()->assertSee('Il Centro');
        }
        $spec = $this->getJson('/developers/openapi.json')->assertOk()->json();
        $this->assertCount(23, $spec['paths']);
        $this->assertSame('3.0.3', $spec['openapi']);
        foreach (CommonApiController::FIELDS as $resource => $fields) {
            $this->assertSame($fields, array_keys($spec['components']['schemas'][$resource]['properties']));
        }
        $this->get('/developers/not-a-page')->assertNotFound();
    }

    public function test_webhook_baseline_updates_signature_and_revocation(): void
    {
        $c = $this->connection(['clients' => ['all' => true]]);
        $id = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $id, 'name' => 'Prima']);
        $this->mock(IntegrationWebhooks::class, function ($mock) {
            $mock->shouldReceive('validateUrl')->andReturn(['example.com', '93.184.216.34']);
        });
        $secret = $this->postJson('/api/v1/webhooks', ['url' => 'https://example.com/events', 'events' => ['clients.updated', 'clients.removed']])->assertCreated()->json('data.secret');
        $this->assertDatabaseCount('api_events', 0);
        $service = app(CommonApiWebhooks::class);
        DB::table('clients')->where('id', $id)->update(['name' => 'Dopo']);
        $service->capture();
        $service->capture();
        $this->assertDatabaseCount('api_events', 1);
        Http::fake(['*' => Http::response('', 204)]);
        $service->deliver();
        Http::assertSent(function ($request) use ($secret) {
            $timestamp = $request->header('X-Centro-Timestamp')[0];

            return hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret), $request->header('X-Centro-Signature')[0]);
        });
        $this->assertDatabaseHas('api_events', ['status' => 'delivered']);
        DB::table('clients')->where('id', $id)->delete();
        $service->capture();
        $c->update(['revoked_at' => now()]);
        $service->deliver();
        $this->assertDatabaseHas('api_events', ['event' => 'clients.removed', 'status' => 'cancelled']);
    }

    public function test_availability_respects_weekend_and_does_not_expose_private_reasons(): void
    {
        $c = $this->connection(['users' => ['all' => true]]);
        $this->getJson('/api/v1/availability?date=2026-11-08')->assertOk()->assertJsonPath('data.0.status', 'not_scheduled')->assertJsonPath('data.0.work_mode', null);
        $this->getJson('/api/v1/availability?date=2026-11-09')->assertOk()->assertJsonPath('data.0.status', 'scheduled');
        DB::table('absence_requests')->insert(['id' => (string) Str::uuid(), 'user_id' => $c->created_by, 'type' => 'sickness', 'status' => 'approved', 'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'notes' => 'PRIVATE']);
        $this->getJson('/api/v1/availability?date=2026-11-09')->assertOk()->assertJsonPath('data.0.status', 'absent')->assertDontSee('PRIVATE')->assertDontSee('sickness');
        $this->getJson('/api/v1/absences?date=2026-11-09')->assertOk()->assertJsonCount(1, 'data')->assertDontSee('PRIVATE')->assertDontSee('sickness');
    }
}
