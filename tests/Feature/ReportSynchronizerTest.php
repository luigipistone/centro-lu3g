<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        return ['id' => 'remote-1', 'name' => 'Acme', 'revision' => 'r1', 'profileRevision' => 'p1', 'servicesRevision' => 's1',
            'status' => 'active', 'servicesComplete' => true, 'source' => ['id' => 'native-1', 'type' => 'company'],
            'profile' => ['fields' => ['vatNumber' => ['availability' => 'available', 'value' => '123']]],
            'activeAreas' => [['id' => 'adv', 'name' => 'ADV']],
            'services' => [['id' => 'contract', 'name' => 'Campaigns', 'status' => 'active', 'areas' => [['id' => 'adv', 'name' => 'ADV']]]]];
    }

    public function test_legal_name_backfill_without_upstream_revision_change_and_missing_preserves_value(): void
    {
        $actor = User::factory()->create()->id;
        $client = $this->fixture();
        $client['activeAreas'] = []; $client['services'] = [];
        $sync = app(ReportSynchronizer::class);
        $sync->run([$client], ['remote-1' => 'new'], [], $actor);
        DB::table('report_client_links')->update(['profile_mapping_version' => 1]);
        $client['profile']['fields']['name'] = ['availability' => 'available', 'value' => 'Acme Servizi S.r.l.'];
        $this->assertSame(1, $sync->run([$client], ['remote-1' => 'new'], [], $actor)['updated']);
        $this->assertDatabaseHas('clients', ['name' => 'ACME', 'legal_name' => 'Acme Servizi S.r.l.']);
        $this->assertSame(1, $sync->run([$client], ['remote-1' => 'new'], [], $actor)['unchanged']);
        $client['profileRevision'] = 'p2';
        $client['profile']['fields']['name'] = ['availability' => 'empty', 'value' => null];
        $sync->run([$client], ['remote-1' => 'new'], [], $actor);
        $this->assertDatabaseHas('clients', ['legal_name' => 'Acme Servizi S.r.l.']);
    }

    public function test_revisions_archive_reactivation_and_manual_ownership(): void
    {
        $actor = User::factory()->create()->id;
        $service = (string) Str::uuid();
        DB::table('services')->insert(['id' => $service, 'name' => 'ADV']);
        $sync = app(ReportSynchronizer::class);
        $c = $this->fixture();
        $run = fn ($data, $choice = 'new') => $sync->run([$data], ['remote-1' => $choice], ['adv' => $service], $actor);
        $this->assertSame(1, $run($c)['created']);
        $local = DB::table('report_client_links')->value('client_id');
        DB::table('clients')->where('id', $local)->update(['notes' => 'Manual note', 'phone' => '555']);
        $this->assertSame(1, $run($c, $local)['unchanged']);
        $c['profileRevision'] = 'p2'; $c['name'] = 'Renamed';
        $this->assertSame(1, $run($c, $local)['updated']);
        $this->assertDatabaseHas('clients', ['id' => $local, 'name' => 'RENAMED', 'phone' => '555', 'notes' => 'Manual note']);
        $this->assertDatabaseHas('client_services', ['client_id' => $local, 'manual_association' => false, 'report_association' => true]);
        $c['servicesRevision'] = 's2'; $c['status'] = 'inactive'; $c['activeAreas'] = []; $c['services'][0]['status'] = 'inactive';
        $this->assertSame(1, $run($c, $local)['updated']);
        $this->assertDatabaseHas('clients', ['id' => $local, 'active' => false, 'notes' => 'Manual note']);
        $this->assertDatabaseCount('client_services', 0);
        $this->assertDatabaseHas('report_client_services', ['external_id' => 'contract', 'status' => 'inactive']);
        $c['servicesRevision'] = 's3'; $c['status'] = 'active'; $c['activeAreas'] = $this->fixture()['activeAreas']; $c['services'][0]['status'] = 'active';
        $run($c, $local);
        $this->assertDatabaseHas('clients', ['id' => $local, 'active' => true]);
        DB::table('client_services')->update(['manual_association' => true]);
        $c['servicesRevision'] = 's4'; $c['activeAreas'] = []; $c['services'][0]['status'] = 'inactive';
        $run($c, $local);
        $this->assertDatabaseHas('client_services', ['client_id' => $local, 'manual_association' => true, 'report_association' => false]);
        $old = (array) DB::table('report_client_links')->first();
        $c['servicesRevision'] = 's5'; $c['profileRevision'] = 'p3'; $c['name'] = 'Must rollback';
        $c['activeAreas'] = [['id' => 'unmapped', 'name' => 'SEO']];
        $this->assertCount(1, $run($c, $local)['errors']);
        $this->assertSame($old, (array) DB::table('report_client_links')->first());
        $this->assertDatabaseHas('clients', ['id' => $local, 'name' => 'RENAMED']);
    }

    public function test_incomplete_or_missing_services_do_not_remove_known_associations(): void
    {
        $actor = User::factory()->create()->id;
        $service = (string) Str::uuid();
        DB::table('services')->insert(['id' => $service, 'name' => 'ADV']);
        $sync = app(ReportSynchronizer::class);
        $c = $this->fixture(); unset($c['profileRevision'], $c['servicesRevision']);
        $sync->run([$c], ['remote-1' => 'new'], ['adv' => $service], $actor);
        $c['revision'] = 'r2'; $c['activeAreas'] = []; $c['services'] = [];
        $sync->run([$c], ['remote-1' => 'new'], [], $actor);
        $this->assertDatabaseCount('client_services', 1);
        $c['revision'] = 'r3'; $c['servicesComplete'] = false; $c['services'] = $this->fixture()['services']; $c['services'][0]['status'] = 'inactive';
        $sync->run([$c], ['remote-1' => 'new'], [], $actor);
        $this->assertDatabaseCount('client_services', 1);
        $this->assertSame(1, $sync->run([$c], [], [], $actor)['skipped']);
    }

    public function test_one_terminated_contract_does_not_remove_still_active_category(): void
    {
        $actor = User::factory()->create()->id;
        $service = (string) Str::uuid();
        DB::table('services')->insert(['id' => $service, 'name' => 'ADV']);
        $sync = app(ReportSynchronizer::class);
        $c = $this->fixture();
        $c['services'][] = [...$c['services'][0], 'id' => 'second-contract', 'status' => 'inactive'];
        $sync->run([$c], ['remote-1' => 'new'], ['adv' => $service], $actor);
        $this->assertDatabaseCount('client_services', 1);
        $this->assertDatabaseCount('report_client_services', 2);
        $c['servicesRevision'] = 's2';
        $sync->run([$c], ['remote-1' => 'new'], [], $actor);
        $this->assertDatabaseHas('client_services', ['service_id' => $service, 'report_association' => true]);
    }

    public function test_no_implicit_association_and_failed_new_client_rolls_back(): void
    {
        $actor = User::factory()->create()->id;
        $sync = app(ReportSynchronizer::class);
        $c = $this->fixture();
        $this->assertSame(1, $sync->run([$c], [], [], $actor)['skipped']);
        $this->assertDatabaseCount('clients', 0);
        $this->assertCount(1, $sync->run([$c], ['remote-1' => 'new'], [], $actor)['errors']);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('report_client_links', 0);
    }
}
