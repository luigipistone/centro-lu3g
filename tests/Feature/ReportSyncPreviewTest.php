<?php

namespace Tests\Feature;

use App\Services\ReportSyncPreview;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportSyncPreviewTest extends TestCase
{
    public function test_name_match_is_only_a_suggestion_and_excluded_areas_are_not_matched(): void
    {
        $result = (new ReportSyncPreview)->build([
            ['id' => 'report', 'name' => ' ACME ', 'activeAreas' => [['id' => 'pec', 'name' => 'PEC']]],
        ], [['id' => 'local', 'name' => 'Acme']], [], [['id' => 'service', 'name' => 'PEC']]);
        $this->assertTrue($result['read_only']);
        $this->assertSame('da confermare', $result['clients'][0]['status']);
        $this->assertNull($result['clients'][0]['client_id']);
        $this->assertSame('local', $result['clients'][0]['candidates'][0]['id']);
        $this->assertTrue($result['areas'][0]['excluded']);
        $this->assertSame([], $result['areas'][0]['candidates']);
    }

    public function test_known_id_wins_over_name(): void
    {
        $result = (new ReportSyncPreview)->build([['id' => 'report', 'name' => 'New name']], [], ['report' => 'local'], []);
        $this->assertSame('collegato', $result['clients'][0]['status']);
        $this->assertSame('local', $result['clients'][0]['client_id']);
    }

    public function test_incomplete_api_snapshot_is_rejected(): void
    {
        config(['report_sync.token' => 'test-only']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['resource' => 'clients.services', 'schemaVersion' => 1,
            'scope' => ['status' => 'all', 'activityYear' => 2026], 'snapshotVersion' => 'v1',
            'page' => ['totalClients' => 1, 'returned' => 0, 'nextCursor' => null], 'clients' => []])]);
        $this->expectExceptionMessage('Elenco Report incompleto.');
        (new ReportSyncPreview)->fetch();
    }
}
