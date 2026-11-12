<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamleaderSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamleaderSyncTest extends TestCase
{
    use RefreshDatabase;

    private function setupConnection(): object
    {
        $user = User::factory()->create(['email' => 'participant@example.com', 'account_status' => 'active']);
        DB::table('user_roles')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'role' => 'superadmin']);
        $this->actingAs($user);
        DB::table('teamleader_settings')->insert(['id' => 1, 'client_id' => 'client', 'client_secret' => Crypt::encryptString('secret'), 'access_token' => Crypt::encryptString('access'),
            'refresh_token' => Crypt::encryptString('refresh'), 'expires_at' => now()->addHour(), 'connection_id' => Str::uuid(),
            'enabled' => true, 'import_from' => '2026-11-01', 'months_ahead' => 12, 'connected_by' => $user->id]);
        return DB::table('teamleader_settings')->find(1);
    }
    private function meeting(array $extra = []): array
    {
        return $extra + ['id' => 'meeting', 'title' => 'Appuntamento', 'description' => '<script>alert(1)</script>Descrizione', 'scheduled_at' => '2026-11-12T10:00:00+01:00',
            'duration' => ['unit' => 'min', 'value' => 60], 'status' => 'open', 'attendees' => [['type' => 'user', 'id' => 'participant']]];
    }
    private function people(): array { return ['participant' => ['email' => 'PARTICIPANT@example.com', 'status' => 'active']]; }
    private function fake(array $meetings, $info = null, int $infoStatus = 404): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.focus.teamleader.eu/users.me' => Http::response(['data' => ['account' => ['id' => 'account']]]),
            'api.focus.teamleader.eu/meetings.list' => Http::response(['data' => $meetings]),
            'api.focus.teamleader.eu/users.info' => Http::response(['data' => $this->people()['participant']]),
            'api.focus.teamleader.eu/meetings.info' => Http::response($info ? ['data' => $info] : [], $infoStatus)]);
    }
    public function test_create_update_and_local_completion_survive_repeated_sync(): void
    {
        $s = $this->setupConnection(); $sync = new TeamleaderSync;
        $this->assertSame(1, $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), [])['created']);
        $task = DB::table('tasks')->first();
        $this->assertSame('09:00:00', substr($task->teamleader_starts_at, 11));
        $this->assertSame('10:00:00', $task->due_time);
        $this->assertStringNotContainsString('<script>', $task->description);
        $this->assertDatabaseCount('task_assignees', 1);
        DB::table('tasks')->where('id', $task->id)->update(['status' => 'done', 'priority' => 'high', 'updated_at' => '2026-01-01 00:00:00']);
        $this->assertSame(1, $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), [])['unchanged']);
        $this->assertSame('2026-01-01 00:00:00', DB::table('tasks')->value('updated_at'));
        $this->assertSame(1, $sync->reconcile($s, 'account', [$this->meeting(['title' => 'Titolo aggiornato'])], $this->people(), [])['updated']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Titolo aggiornato', 'status' => 'done', 'priority' => 'high']);
        $this->assertSame(1, $sync->reconcile($s, 'account', [], [], ['meeting'])['cancelled']);
        $this->assertNotNull(DB::table('tasks')->value('teamleader_cancelled_at'));
        $this->assertSame(1, $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), [])['updated']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'teamleader_cancelled_at' => null, 'status' => 'done']);
        $this->assertDatabaseCount('tasks', 1);
    }
    public function test_remote_completion_updates_unchanged_meeting_and_never_reopens_it(): void
    {
        $s = $this->setupConnection(); $sync = new TeamleaderSync;
        $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), []);
        $s = DB::table('teamleader_settings')->find(1);
        $id = DB::table('tasks')->value('id');
        DB::table('tasks')->where('id', $id)->update(['priority' => 'high']);
        $this->assertSame(1, $sync->reconcile($s, 'account', [$this->meeting(['status' => 'done'])], $this->people(), [])['updated']);
        $this->assertDatabaseHas('tasks', ['id' => $id, 'status' => 'done', 'priority' => 'high']);
        $this->assertDatabaseHas('teamleader_notices', ['task_id' => $id, 'message' => 'Meeting completato su Teamleader']);
        $this->assertSame(1, $sync->reconcile($s, 'account', [$this->meeting(['status' => 'done'])], $this->people(), [])['unchanged']);
        $this->assertDatabaseCount('teamleader_notices', 1);
        $this->assertSame(1, $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), [])['unchanged']);
        $sync->reconcile($s, 'account', [$this->meeting(['title' => 'Nuovo titolo'])], $this->people(), []);
        $this->assertDatabaseHas('tasks', ['id' => $id, 'status' => 'done', 'title' => 'Nuovo titolo', 'priority' => 'high']);
    }

    public function test_missing_meeting_is_verified_and_moved_meeting_is_not_deleted(): void
    {
        $s = $this->setupConnection(); $sync = new TeamleaderSync;
        $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), []);
        $this->fake([], $this->meeting(['scheduled_at' => '2029-01-10T10:00:00+01:00']), 200);
        $result = $sync->run();
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['cancelled']);
        $this->assertDatabaseHas('tasks', ['start_date' => '2029-01-10', 'teamleader_cancelled_at' => null]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/meetings.info') && $r['id'] === 'meeting');
    }
    public function test_confirmed_deletion_hides_meeting_without_destroying_history(): void
    {
        $s = $this->setupConnection(); $sync = new TeamleaderSync;
        $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), []);
        $this->fake([]);
        $this->assertSame(1, $sync->run()['cancelled']);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertNotNull(DB::table('tasks')->value('teamleader_cancelled_at'));
        $this->assertDatabaseCount('task_assignees', 1);
        $id = DB::table('tasks')->value('id');
        foreach (['/tasks', '/calendar'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('rows', fn ($rows) => ! collect($rows)->contains('id', $id))->etc());
        }
    }
    public function test_failed_lookup_does_not_apply_any_partial_changes(): void
    {
        $s = $this->setupConnection(); $sync = new TeamleaderSync;
        $sync->reconcile($s, 'account', [$this->meeting()], $this->people(), []);
        $before = DB::table('tasks')->get()->toJson();
        $this->fake([$this->meeting(['id' => 'new'])], null, 503);
        $this->assertSame(1, $sync->run()['errors']);
        $this->assertSame($before, DB::table('tasks')->get()->toJson());
        $this->assertNotNull(DB::table('teamleader_settings')->value('last_error'));
    }
    public function test_all_pages_are_read_and_partial_pagination_failure_is_not_committed(): void
    {
        $this->setupConnection();
        $batch = array_map(fn ($i) => $this->meeting(['id' => 'm'.$i]), range(1, 100));
        Http::fake(['api.focus.teamleader.eu/users.me' => Http::response(['data' => ['account' => ['id' => 'account']]]),
            'api.focus.teamleader.eu/meetings.list' => Http::sequence()->push(['data' => $batch])->push([], 500)]);
        $this->assertSame(1, (new TeamleaderSync)->run()['errors']);
        $this->assertDatabaseCount('tasks', 0);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/meetings.list') && $r['page']['number'] === 2);
    }
    public function test_meeting_source_fields_are_locked_but_centro_fields_are_editable(): void
    {
        $s = $this->setupConnection();
        (new TeamleaderSync)->reconcile($s, 'account', [$this->meeting()], $this->people(), []);
        $id = DB::table('tasks')->value('id');
        $this->put('/tasks/'.$id, ['title' => 'Forbidden'])->assertStatus(409);
        $this->put('/tasks/'.$id, ['status' => 'done', 'priority' => 'high'])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $id, 'status' => 'done', 'priority' => 'high']);
        $this->deleteJson('/tasks/'.$id, ['confirmation' => 'ELIMINA'])->assertStatus(409);
    }
    public function test_source_creator_is_never_an_assignee(): void
    {
        $s = $this->setupConnection();
        (new TeamleaderSync)->reconcile($s, 'account', [$this->meeting(['attendees' => [], 'creator' => ['email' => 'participant@example.com']])], [], []);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('task_assignees', 0);
    }
    public function test_different_account_cannot_reconcile_previous_meetings(): void
    {
        $s = $this->setupConnection(); $sync = new TeamleaderSync;
        $sync->reconcile($s, 'original-account', [$this->meeting()], $this->people(), []);
        $before = DB::table('tasks')->get()->toJson();
        $this->fake([]);
        $this->assertSame(1, $sync->run()['errors']);
        $this->assertSame($before, DB::table('tasks')->get()->toJson());
        Http::assertSentCount(1);
    }
    public function test_successful_multiple_pages_are_all_imported(): void
    {
        $this->setupConnection();
        $batch = array_map(fn ($i) => $this->meeting(['id' => 'm'.$i]), range(1, 100));
        Http::fake(['api.focus.teamleader.eu/users.me' => Http::response(['data' => ['account' => ['id' => 'account']]]),
            'api.focus.teamleader.eu/meetings.list' => Http::sequence()->push(['data' => $batch])->push(['data' => [$this->meeting(['id' => 'last'])]]),
            'api.focus.teamleader.eu/users.info' => Http::response(['data' => $this->people()['participant']])]);
        $this->assertSame(101, (new TeamleaderSync)->run()['created']);
        $this->assertDatabaseCount('tasks', 101);
        Http::assertSentCount(4);
    }
}
