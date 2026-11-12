<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamleaderPreviewClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamleaderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['account_status' => 'active']);
        DB::table('user_roles')->insert(['id' => Str::uuid(), 'user_id' => $u->id, 'role' => 'superadmin']);
        return $u;
    }
    private function connection(): string
    {
        $id = (string) Str::uuid();
        DB::table('teamleader_settings')->insert(['id' => 1, 'client_id' => 'client', 'client_secret' => Crypt::encryptString('secret'),
            'access_token' => Crypt::encryptString('access'), 'refresh_token' => Crypt::encryptString('refresh'),
            'expires_at' => now()->addHour(), 'connection_id' => $id]);
        return $id;
    }
    public function test_only_superadmin_can_access_connection_and_preview(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/settings/teamleader')->assertForbidden();
        $this->putJson('/settings/teamleader/credentials', [])->assertForbidden();
        $this->postJson('/settings/teamleader/simulate', [])->assertForbidden();
        $this->get('/settings/teamleader/connect')->assertForbidden();
        $this->get('/settings/teamleader/callback')->assertForbidden();
        $this->deleteJson('/settings/teamleader')->assertForbidden();
    }
    public function test_credentials_are_encrypted_and_never_returned(): void
    {
        $this->actingAs($this->admin())->putJson('/settings/teamleader/credentials', ['client_id' => 'client', 'client_secret' => 'my-secret'])
            ->assertOk()->assertJson(['has_secret' => true, 'connected' => false])->assertJsonMissing(['client_secret' => 'my-secret']);
        $this->assertSame('my-secret', Crypt::decryptString(DB::table('teamleader_settings')->value('client_secret')));
        $this->assertStringNotContainsString('my-secret', $this->getJson('/settings/teamleader')->getContent());
    }
    public function test_oauth_callback_is_single_use_and_refresh_token_rotates(): void
    {
        $this->actingAs($this->admin()); $this->connection();
        $this->get('/settings/teamleader/connect')->assertRedirect();
        $state = session('teamleader_oauth.state');
        Http::preventStrayRequests();
        Http::fake(['focus.teamleader.eu/oauth2/access_token' => Http::sequence()
            ->push(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600])
            ->push(['access_token' => 'rotated-access', 'refresh_token' => 'rotated-refresh', 'expires_in' => 3600]),
            'api.focus.teamleader.eu/meetings.list' => Http::response(['data' => []])]);
        $this->get('/settings/teamleader/callback?code=code&state='.$state)->assertRedirect('/settings?tab=teamleader');
        $this->get('/settings/teamleader/callback?code=code&state='.$state)->assertForbidden();
        $this->assertSame('new-refresh', Crypt::decryptString(DB::table('teamleader_settings')->value('refresh_token')));
        DB::table('teamleader_settings')->update(['expires_at' => now()->subMinute()]);
        (new TeamleaderPreviewClient)->read('meetings.list', []);
        $this->assertSame('rotated-refresh', Crypt::decryptString(DB::table('teamleader_settings')->value('refresh_token')));
        Http::assertSent(fn ($r) => ($r['grant_type'] ?? '') === 'refresh_token' && $r['refresh_token'] === 'new-refresh');
    }
    public function test_preview_matches_only_participants_without_writing_tasks(): void
    {
        $u = $this->admin(); $id = $this->connection();
        $before = DB::table('tasks')->get()->toJson();
        Http::preventStrayRequests();
        Http::fake(['api.focus.teamleader.eu/meetings.list' => Http::response(['data' => [
            ['id' => 'meeting', 'title' => 'Preview', 'scheduled_at' => '2026-11-12T10:00:00+01:00', 'duration' => ['value' => 30],
                'status' => 'open', 'attendees' => [['type' => 'user', 'id' => 'participant'], ['type' => 'contact', 'id' => 'external']],
                'creator' => ['email' => 'ignored@example.com']],
            ['id' => 'meeting2', 'title' => 'No participant', 'scheduled_at' => '2026-11-12T12:00:00+01:00', 'attendees' => [], 'creator' => ['email' => $u->email]],
        ]]), 'api.focus.teamleader.eu/users.info' => Http::response(['data' => ['email' => strtoupper($u->email), 'status' => 'active']])]);
        $this->actingAs($u)->postJson('/settings/teamleader/simulate', ['from' => '2026-11-01', 'to' => '2026-12-01', 'page' => 2, 'connection_id' => $id])
            ->assertOk()->assertJsonPath('rows.0.assignees.0', $u->name)->assertJsonPath('rows.1.assignees', [])->assertJsonPath('read_only', true);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'meetings.list') && $r['page']['number'] === 2);
        Http::assertSentCount(2);
        $this->assertSame($before, DB::table('tasks')->get()->toJson());
    }
    public function test_preview_failure_and_validation_do_not_write_tasks(): void
    {
        $this->actingAs($this->admin()); $id = $this->connection();
        Http::fake(['*' => Http::response([], 403)]);
        $payload = ['from' => '2026-11-12', 'to' => '2026-11-30', 'page' => 1, 'connection_id' => $id];
        $this->postJson('/settings/teamleader/simulate', $payload)->assertStatus(502);
        $this->postJson('/settings/teamleader/simulate', array_replace($payload, ['to' => '2026-01-01']))->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 0);
        $this->deleteJson('/settings/teamleader')->assertOk()->assertJsonPath('connected', false);
        $this->postJson('/settings/teamleader/simulate', $payload)->assertStatus(409);
    }
}
