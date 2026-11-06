<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportAutomaticSync;
use App\Services\ReportSyncPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportAutomaticSyncTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $id, string $name = 'Acme'): array
    {
        return ['id' => $id, 'name' => $name, 'revision' => 'r1', 'status' => 'active', 'servicesComplete' => true,
            'activeAreas' => [['id' => 'adv', 'name' => 'ADV']], 'services' => [], 'profile' => []];
    }

    private function runRemote(array $clients, string $actor): array
    {
        $this->mock(ReportSyncPreview::class, fn ($mock) => $mock->shouldReceive('fetch')->once()->andReturn($clients));
        return app(ReportAutomaticSync::class)->execute($actor);
    }

    public function test_auto_creation_matching_archive_and_return_preserve_history(): void
    {
        $actor = User::factory()->create()->id;
        $local = (string) Str::uuid();
        $manual = (string) Str::uuid();
        DB::table('clients')->insert([
            ['id' => $local, 'name' => 'Acme', 'notes' => 'Preserve'],
            ['id' => $manual, 'name' => 'Manual only', 'notes' => 'Keep'],
        ]);
        $a = $this->client('a'); $b = $this->client('b', 'Other'); $b['status'] = 'inactive';
        $result = $this->runRemote([$a, $b], $actor);
        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('report_client_links', ['external_id' => 'a', 'client_id' => $local]);
        $this->assertDatabaseCount('services', 1);
        $this->assertSame(2, $this->runRemote([$a, $b], $actor)['unchanged']);
        $this->assertSame(1, $this->runRemote([$b], $actor)['archived']);
        $this->assertDatabaseHas('clients', ['id' => $local, 'active' => false, 'notes' => 'Preserve']);
        $this->assertDatabaseHas('clients', ['id' => $manual, 'active' => true]);
        $this->runRemote([$a, $b], $actor);
        $this->assertDatabaseHas('clients', ['id' => $local, 'active' => true, 'notes' => 'Preserve']);
        $this->assertDatabaseCount('clients', 3);
    }

    public function test_ambiguous_names_never_merge_or_create_duplicates(): void
    {
        DB::table('clients')->insert(['id' => (string) Str::uuid(), 'name' => 'Acme']);
        $plan = app(ReportAutomaticSync::class)->plan([$this->client('a'), $this->client('b')]);
        $this->assertSame([], $plan['choices']);
        $this->assertCount(2, $plan['errors']);
    }

    public function test_empty_or_failed_fetch_cannot_archive_clients(): void
    {
        $actor = User::factory()->create()->id;
        $this->runRemote([$this->client('a')], $actor);
        try { $this->runRemote([], $actor); $this->fail('Empty source accepted'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('vuoto', $e->getMessage()); }
        $this->assertDatabaseHas('clients', ['name' => 'ACME', 'active' => true]);
    }

    public function test_automatic_endpoint_requires_superadmin(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('settings.report.automatic'))->assertForbidden();
    }
}
