<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportSyncPreview;
use App\Services\ReportSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
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
            $this->postJson(route('settings.report.synchronize'))->assertForbidden();
            $this->putJson(route('settings.report.update'), ['token' => 'secret'])->assertForbidden();
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

    public function test_sync_requires_own_unexpired_preview_and_consumes_it(): void
    {
        $actor = $this->actor('superadmin');
        $snapshot = (string) Str::uuid();
        $payload = ['snapshot' => $snapshot, 'clients' => ['r' => 'new'], 'areas' => []];
        $this->actingAs($actor)->postJson(route('settings.report.synchronize'), $payload)->assertStatus(409);
        $client = ['id' => 'r', 'name' => 'Example', 'revision' => 'v1', 'status' => 'inactive', 'servicesComplete' => true, 'activeAreas' => [], 'services' => []];
        Cache::put('report-preview:'.$actor->id.':'.$snapshot, [$client], 60);
        $this->postJson(route('settings.report.synchronize'), $payload)->assertOk()->assertJsonPath('created', 1);
        $this->assertDatabaseHas('clients', ['name' => 'EXAMPLE', 'active' => false]);
        $this->postJson(route('settings.report.synchronize'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_token_is_encrypted_replaceable_and_never_returned(): void
    {
        config(['report_sync.token' => 'environment-fallback']);
        $this->actingAs($this->actor('superadmin'));
        $this->putJson(route('settings.report.update'), ['token' => 'test-secret'])
            ->assertOk()->assertDontSee('test-secret');
        $encrypted = DB::table('report_settings')->value('encrypted_token');
        $this->assertNotSame('test-secret', $encrypted);
        $this->assertSame('test-secret', Crypt::decryptString($encrypted));
        $this->assertSame('test-secret', app(ReportSettings::class)->token());
        $this->putJson(route('settings.report.update'), ['token' => 'replacement'])->assertOk();
        $this->assertDatabaseCount('report_settings', 1);
        $this->assertSame('replacement', app(ReportSettings::class)->token());
        $invalid = $this->putJson(route('settings.report.update'), ['token' => '']);
        $this->assertSame(422, $invalid->getStatusCode(), $invalid->getContent());
        $this->assertSame('replacement', app(ReportSettings::class)->token());
        $this->assertDatabaseCount('report_client_links', 0);
        $this->get(route('settings.report.index'))->assertDontSee('replacement')
            ->assertInertia(fn (Assert $page) => $page->where('configured', true));
    }
}
