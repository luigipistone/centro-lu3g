<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportSyncPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportSyncAccessTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
        return $user;
    }

    public function test_only_superadmin_can_read_preview(): void
    {
        Http::preventStrayRequests();
        foreach (['admin', 'editor', 'guest'] as $role) {
            $this->actingAs($this->actor($role))->get(route('settings.report.index'))->assertForbidden();
            $this->postJson(route('settings.report.preview'))->assertForbidden();
        }
        $this->actingAs($this->actor('superadmin'))->get(route('settings.report.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Centro/ReportSync')->where('configured', false));
    }

    public function test_preview_does_not_create_records_or_disclose_secret(): void
    {
        $this->partialMock(ReportSyncPreview::class, function ($mock) {
            $mock->shouldReceive('fetch')->once()->andReturn([['id' => 'remote-id', 'name' => 'REMOTE']]);
        });
        $before = DB::table('clients')->count();
        $this->actingAs($this->actor('superadmin'))->postJson(route('settings.report.preview'))
            ->assertOk()->assertJsonPath('read_only', true)
            ->assertJsonPath('clients.0.status', 'senza corrispondenza');
        $this->assertSame($before, DB::table('clients')->count());
        $this->assertDatabaseCount('report_client_links', 0);
        $this->assertDatabaseCount('report_sync_runs', 0);
    }

    public function test_failure_is_sanitized(): void
    {
        $this->partialMock(ReportSyncPreview::class, function ($mock) {
            $mock->shouldReceive('fetch')->once()->andThrow(new \RuntimeException('secret-token'));
        });
        $this->actingAs($this->actor('superadmin'))->postJson(route('settings.report.preview'))
            ->assertStatus(422)->assertDontSee('secret-token');
    }
}
