<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleCalendarSync;
use Carbon\CarbonImmutable as Date;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'superadmin', string $email = 'staff@example.com'): User
    {
        $u = User::factory()->create(['email' => $email]);
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'role' => $role]);
        return $u;
    }

    private function settings(array $extra = []): object
    {
        DB::table('google_calendar_settings')->updateOrInsert(['id' => 1], $extra + ['client_id' => 'client', 'client_secret' => Crypt::encryptString('secret'), 'refresh_token' => Crypt::encryptString('refresh'), 'calendar_id' => 'team@example.com', 'timezone' => 'Europe/Rome', 'enabled' => true, 'import_from' => '2026-01-01', 'months_ahead' => 12]);
        return DB::table('google_calendar_settings')->first();
    }

    private function event(string $id = 'event1', array $extra = []): array
    {
        return $extra + ['id' => $id, 'summary' => 'Riunione cliente', 'start' => ['dateTime' => '2026-11-10T09:00:00+01:00'], 'end' => ['dateTime' => '2026-11-10T10:30:00+01:00'], 'attendees' => [['email' => 'staff@example.com']], 'htmlLink' => 'https://calendar.google.com/calendar/event?eid=123', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij'];
    }

    private function reconcile(array $events, ?object $settings = null): array
    {
        return app(GoogleCalendarSync::class)->reconcile($settings ?? $this->settings(), $events, 'next-token', Date::parse('2026-01-01', 'Europe/Rome'), Date::parse('2027-12-01', 'Europe/Rome'));
    }

    public function test_only_superadmin_can_configure_and_secrets_are_not_returned(): void
    {
        $u = $this->user('admin');
        $this->actingAs($u)->getJson('/settings/google-calendar')->assertForbidden();
        DB::table('user_roles')->where('user_id', $u->id)->update(['role' => 'superadmin']);
        $this->putJson('/settings/google-calendar/credentials', ['client_id' => 'client', 'client_secret' => 'my-secret'])->assertOk()->assertJsonPath('has_secret', true)->assertDontSee('my-secret');
        $this->assertSame('my-secret', Crypt::decryptString(DB::table('google_calendar_settings')->value('client_secret')));
        $this->assertStringNotContainsString('my-secret', DB::table('audit_logs')->get()->toJson());
    }

    public function test_last_sync_timestamp_is_utc_in_winter_and_summer(): void
    {
        config(['app.timezone' => 'Europe/Rome']);
        $this->actingAs($this->user());
        foreach (['2026-11-10 14:52:02' => '2026-11-10 13:52:02', '2026-07-10 14:52:02' => '2026-07-10 12:52:02'] as $stored => $utc) {
            $this->settings(['last_synced_at' => $stored]);
            $this->getJson('/settings/google-calendar')->assertOk()->assertJsonPath('last_synced_at', $utc);
        }
        $this->settings(['last_synced_at' => null]);
        $this->getJson('/settings/google-calendar')->assertOk()->assertJsonPath('last_synced_at', null);
    }

    public function test_oauth_state_is_single_use_and_offline_read_only(): void
    {
        $this->actingAs($this->user()); $this->settings(['refresh_token' => null]);
        $res = $this->get('/settings/google-calendar/connect')->assertRedirect();
        parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('offline', $query['access_type']);
        $this->assertStringContainsString('calendar.events.readonly', $query['scope']);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['refresh_token' => 'new-refresh', 'access_token' => 'access'])]);
        $this->get('/settings/google-calendar/callback?code=code&state='.$query['state'])->assertRedirect('/settings?tab=google-calendar');
        $this->assertSame('new-refresh', Crypt::decryptString(DB::table('google_calendar_settings')->value('refresh_token')));
        $this->get('/settings/google-calendar/callback?code=code&state='.$query['state'])->assertForbidden();
    }

    public function test_create_then_unchanged_has_no_task_writes_or_initial_notifications(): void
    {
        $u = $this->user();
        $result = $this->reconcile([$this->event()]);
        $this->assertSame(1, $result['created']);
        $task = DB::table('tasks')->first();
        $this->assertSame('08:00:00', substr($task->google_starts_at, 11));
        $this->assertSame('09:00:00', $task->due_time);
        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $u->id]);
        $this->assertDatabaseCount('google_calendar_notices', 0);
        $this->travel(1)->minutes();
        $result = $this->reconcile([$this->event()], DB::table('google_calendar_settings')->first());
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame($task->updated_at, DB::table('tasks')->value('updated_at'));
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_updates_and_cancellations_preserve_local_data_and_restore_same_task(): void
    {
        $u = $this->user(); $this->reconcile([$this->event()]);
        $task = DB::table('tasks')->first();
        DB::table('tasks')->where('id', $task->id)->update(['status' => 'done']);
        DB::table('task_comments')->insert(['id' => (string) Str::uuid(), 'task_id' => $task->id, 'user_id' => $u->id, 'content' => 'Commento locale', 'created_at' => now(), 'updated_at' => now()]);
        $this->reconcile([$this->event('event1', ['summary' => 'Titolo aggiornato'])]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Titolo aggiornato', 'status' => 'done']);
        $result = $this->reconcile([$this->event('event1', ['status' => 'cancelled'])]);
        $this->assertSame(1, $result['cancelled']);
        $this->assertNotNull(DB::table('tasks')->value('google_cancelled_at'));
        $this->assertDatabaseCount('task_comments', 1);
        $this->reconcile([$this->event()]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'google_cancelled_at' => null]);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_email_matching_excludes_declined_resources_and_inactive_accounts(): void
    {
        $u = $this->user(); $inactive = $this->user('editor', 'inactive@example.com');
        DB::table('users')->where('id', $inactive->id)->update(['account_status' => 'suspended']);
        $result = $this->reconcile([$this->event('event1', ['organizer' => ['email' => 'STAFF@example.com'], 'attendees' => [['email' => 'inactive@example.com'], ['email' => 'external@example.com'], ['email' => 'room@example.com', 'resource' => true]]])]);
        $this->assertSame(['inactive@example.com', 'external@example.com'], $result['unmatched']);
        $this->assertDatabaseCount('task_assignees', 1);
        $this->assertDatabaseHas('task_assignees', ['user_id' => $u->id]);
        $this->reconcile([$this->event('event1', ['attendees' => [['email' => 'staff@example.com', 'responseStatus' => 'declined']], 'organizer' => ['email' => 'staff@example.com']])]);
        $this->assertDatabaseCount('task_assignees', 0);
    }

    public function test_all_day_end_is_exclusive_and_html_is_sanitized(): void
    {
        $this->reconcile([$this->event('allday', ['start' => ['date' => '2026-03-28'], 'end' => ['date' => '2026-03-31'], 'description' => '<img src=x onerror=alert(1)>Test &lt;script&gt;', 'htmlLink' => 'javascript:alert(1)'])]);
        $this->assertDatabaseHas('tasks', ['start_date' => '2026-03-28', 'due_date' => '2026-03-30', 'due_time' => null, 'google_all_day' => true, 'google_event_url' => null]);
        $this->assertStringNotContainsString('<script>', DB::table('tasks')->value('description'));
    }

    public function test_recurrence_instances_and_rescheduled_exception_keep_identity(): void
    {
        $this->reconcile([$this->event('series_20261110'), $this->event('series_20261117')]);
        $id = DB::table('tasks')->where('google_event_id', 'series_20261117')->value('id');
        $this->reconcile([$this->event('series_20261117', ['start' => ['dateTime' => '2026-11-18T14:00:00+01:00'], 'end' => ['dateTime' => '2026-11-18T15:00:00+01:00']])]);
        $this->assertDatabaseHas('tasks', ['id' => $id, 'start_date' => '2026-11-18']);
        $this->assertDatabaseCount('tasks', 2);
        $this->assertSame(1, DB::table('tasks')->whereNull('google_cancelled_at')->count());
    }

    public function test_failure_on_second_page_does_not_cancel_or_advance_cursor(): void
    {
        $this->settings(['sync_token' => 'old', 'requested_at' => now()]); $this->reconcile([$this->event()]);
        DB::table('google_calendar_settings')->update(['sync_token' => 'old']);
        Http::fake(function ($r) {
            if (str_contains($r->url(), '/token')) return Http::response(['access_token' => 'access']);
            if (($r['singleEvents'] ?? '') === 'false') return Http::response(['items' => [['id' => 'event1']], 'nextSyncToken' => 'new']);
            if (isset($r['pageToken'])) return Http::response([], 503);
            return Http::response(['items' => [], 'nextPageToken' => 'page2']);
        });
        $this->assertSame(['errors' => 1], app(GoogleCalendarSync::class)->run());
        $this->assertSame('old', DB::table('google_calendar_settings')->value('sync_token'));
        $this->assertNull(DB::table('tasks')->value('google_cancelled_at'));
    }

    public function test_expired_sync_token_resets_safely_and_reads_all_pages(): void
    {
        $this->settings(['sync_token' => 'expired']);
        Http::fake(function ($r) {
            if (str_contains($r->url(), '/token')) return Http::response(['access_token' => 'access']);
            if (($r['syncToken'] ?? '') === 'expired') return Http::response([], 410);
            if (($r['singleEvents'] ?? '') === 'false') return Http::response(['items' => [], 'nextSyncToken' => 'fresh']);
            return isset($r['pageToken']) ? Http::response(['items' => [$this->event('second')]]) : Http::response(['items' => [$this->event()], 'nextPageToken' => 'two']);
        });
        $result = app(GoogleCalendarSync::class)->run();
        $this->assertSame(2, $result['created']);
        $this->assertSame('fresh', DB::table('google_calendar_settings')->value('sync_token'));
    }

    public function test_webhook_authentication_and_task_write_protection(): void
    {
        $this->actingAs($this->user());
        $this->settings(['channel_id' => 'channel', 'channel_token' => 'secret', 'resource_id' => 'resource']);
        $this->postJson('/api/google-calendar/watch')->assertForbidden();
        $this->withHeaders(['X-Goog-Channel-ID' => 'channel', 'X-Goog-Channel-Token' => 'secret', 'X-Goog-Resource-ID' => 'resource'])->postJson('/api/google-calendar/watch')->assertNoContent();
        $this->reconcile([$this->event()]); $id = DB::table('tasks')->value('id');
        $this->patchJson('/tasks/'.$id.'/schedule', ['start_date' => '2026-11-11', 'due_date' => '2026-11-11'])->assertStatus(409);
        $this->putJson('/tasks/'.$id, ['title' => 'Locale'])->assertStatus(409);
        $this->deleteJson('/tasks/'.$id, ['confirmation' => 'ELIMINA'])->assertStatus(409);
        $this->putJson('/tasks/'.$id.'/people/assignees', ['user_ids' => []])->assertStatus(409);
    }

    public function test_calendar_selection_validates_access_and_keeps_old_meetings_in_history(): void
    {
        $this->actingAs($this->user()); $this->reconcile([$this->event()]);
        $oldId = DB::table('tasks')->value('id');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']), 'www.googleapis.com/calendar/v3/users/me/calendarList/*' => Http::response(['id' => 'other', 'summary' => 'Altro calendario', 'accessRole' => 'reader', 'timeZone' => 'Europe/Rome'])]);
        $this->putJson('/settings/google-calendar', ['calendar_id' => 'other', 'import_from' => '2026-01-01', 'months_ahead' => 6, 'enabled' => true])->assertOk()->assertJsonPath('calendar_id', 'other');
        $this->assertNotNull(DB::table('tasks')->where('id', $oldId)->value('google_cancelled_at'));
        $this->assertNull(DB::table('google_calendar_settings')->value('sync_token'));
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_sync_error_and_invalid_event_do_not_partially_write(): void
    {
        $s = $this->settings(['sync_token' => 'unchanged']);
        try {
            $this->reconcile([$this->event(), ['id' => 'invalid']], $s);
            $this->fail('Invalid event should fail validation');
        } catch (\RuntimeException) { }
        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame('unchanged', DB::table('google_calendar_settings')->value('sync_token'));
    }

    public function test_change_notices_include_removed_attendees_and_followers_only_after_first_import(): void
    {
        $old = $this->user(); $new = $this->user('editor', 'new@example.com');
        $follower = $this->user('editor', 'follower@example.com');
        $this->reconcile([$this->event()]);
        $taskId = DB::table('tasks')->value('id');
        DB::table('task_followers')->insert(['id' => (string) Str::uuid(), 'task_id' => $taskId, 'user_id' => $follower->id]);
        $settings = DB::table('google_calendar_settings')->first();
        $updated = $this->event('event1', ['summary' => 'Modificato', 'start' => ['dateTime' => '2027-01-10T10:00:00+01:00'], 'end' => ['dateTime' => '2027-01-10T11:00:00+01:00'], 'attendees' => [['email' => 'new@example.com']]]);
        $this->reconcile([$updated], $settings);
        $notice = DB::table('google_calendar_notices')->first();
        $this->assertEqualsCanonicalizing([$old->id, $new->id, $follower->id], json_decode($notice->user_ids, true));
        $this->reconcile([$updated], DB::table('google_calendar_settings')->first());
        $this->assertDatabaseCount('google_calendar_notices', 1);
    }

    public function test_unmodified_incremental_sync_avoids_expanded_read_and_task_writes(): void
    {
        $this->reconcile([$this->event()]);
        DB::table('google_calendar_settings')->update(['requested_at' => null]);
        $updated = DB::table('tasks')->value('updated_at');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']), 'www.googleapis.com/calendar/v3/*' => Http::response(['items' => [], 'nextSyncToken' => 'fresh'])]);
        app(GoogleCalendarSync::class)->run();
        Http::assertSentCount(2);
        $this->assertSame($updated, DB::table('tasks')->value('updated_at'));
        $this->assertSame('fresh', DB::table('google_calendar_settings')->value('sync_token'));
    }

    public function test_configuration_during_sync_returns_conflict_not_server_error(): void
    {
        $this->actingAs($this->user()); $this->settings();
        $lock = \Illuminate\Support\Facades\Cache::lock(GoogleCalendarSync::LOCK, 60); $lock->get();
        try { $this->deleteJson('/settings/google-calendar')->assertStatus(409); }
        finally { $lock->release(); }
        $this->assertNotNull(DB::table('google_calendar_settings')->value('refresh_token'));
    }

    public function test_cancelled_meetings_are_absent_from_calendar_and_tasks_but_history_remains(): void
    {
        $this->actingAs($this->user()); $this->reconcile([$this->event()]); $id = DB::table('tasks')->value('id');
        $this->reconcile([]);
        foreach (['/tasks', '/calendar'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('rows', fn ($rows) => ! collect($rows)->contains('id', $id))->etc());
        }
        $this->get('/tasks/'.$id)->assertOk();
    }
}
