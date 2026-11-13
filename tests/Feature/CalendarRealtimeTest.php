<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CalendarRealtime;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Pusher\Pusher;
use Tests\TestCase;

class CalendarRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_channel_requires_session_and_calendar_permissions(): void
    {
        config(['calendar-realtime.enabled' => true]);
        $payload = ['socket_id' => '123.456', 'channel_name' => 'private-calendar'];
        $this->postJson('/calendar/realtime/auth', $payload)->assertRedirect();
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'superadmin']);
        config(['reverb.apps.apps.0.key' => 'test-key', 'reverb.apps.apps.0.secret' => 'test-secret', 'reverb.apps.apps.0.app_id' => 'test']);
        $this->actingAs($user)->postJson('/calendar/realtime/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/calendar/realtime/auth', ['socket_id' => '123.456', 'channel_name' => 'private-other'])->assertForbidden();
        $this->getJson('/calendar/live-data')->assertOk()->assertJsonStructure(['rows']);
        DB::table('user_roles')->where('user_id', $user->id)->update(['role' => 'editor']);
        DB::table('role_permissions')->where('role', 'editor')->where('permission', 'calendar.view')->update(['allowed' => false]);
        $this->postJson('/calendar/realtime/auth', $payload)->assertForbidden();
        $this->getJson('/calendar/live-data')->assertForbidden();
        DB::table('role_permissions')->where('role', 'editor')->where('permission', 'tasks.view')->update(['allowed' => true]);
        DB::table('role_permissions')->where('role', 'editor')->whereIn('permission', ['tasks.create', 'tasks.update'])->update(['allowed' => false]);
        $this->postJson('/tasks/realtime/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
        $this->getJson('/tasks/live-data')->assertOk()->assertJsonStructure(['rows']);
        DB::table('role_permissions')->where('role', 'editor')->where('permission', 'tasks.view')->update(['allowed' => false]);
        $this->postJson('/tasks/realtime/auth', $payload)->assertForbidden();
        $this->getJson('/tasks/live-data')->assertForbidden();
    }

    public function test_coalesces_committed_writes_and_ignores_reads(): void
    {
        config(['calendar-realtime.enabled' => true]);
        $pusher = Mockery::mock(Pusher::class);
        $pusher->shouldReceive('trigger')->once()->with('private-calendar', 'calendar.changed', []);
        $service = Mockery::mock(CalendarRealtime::class)->makePartial();
        $service->shouldReceive('client')->andReturn($pusher);
        $connection = Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getName')->andReturn('test');
        $connection->shouldReceive('transactionLevel')->andReturn(0);
        foreach (['select * from tasks', 'update "tasks" set title = ?', 'delete from task_assignees where task_id = ?', 'insert into `tasks` (id) values (?)'] as $sql) {
            $service->observe(new QueryExecuted($sql, [], 0, $connection));
        }
        $service->flush();
        $service->flush();
    }

    public function test_waits_for_commit_and_discarded_callback_does_not_publish(): void
    {
        config(['calendar-realtime.enabled' => true]);
        $service = Mockery::mock(CalendarRealtime::class)->makePartial();
        $service->shouldNotReceive('client');
        $connection = Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getName')->andReturn('test');
        $connection->shouldReceive('transactionLevel')->andReturn(1);
        $connection->shouldReceive('afterCommit')->once()->with(Mockery::type('Closure'));
        $service->observe(new QueryExecuted('update tasks set title = ?', [], 0, $connection));
        $service->flush();
    }
}
