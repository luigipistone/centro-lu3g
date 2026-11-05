<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ReportSyncPreview
{
    public function fetch(): array
    {
        $token = app(ReportSettings::class)->token();
        if (! $token) {
            throw new RuntimeException('Configura il token nelle impostazioni LU3G Report.');
        }
        $clients = $ids = $cursors = [];
        $status = config('report_sync.status', 'active');
        $snapshot = null;
        $cursor = null;
        for ($page = 0; $page < config('report_sync.max_pages', 200); $page++) {
            $query = ['status' => $status, 'limit' => config('report_sync.page_size', 100)];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }
            $response = Http::withToken($token)->acceptJson()->timeout(30)
                ->withOptions(['allow_redirects' => false])
                ->get(config('report_sync.endpoint'), $query);
            if (! $response->successful()) {
                throw new RuntimeException('Lettura Report fallita: HTTP '.$response->status());
            }
            $data = $response->json();
            $total = data_get($data, 'page.totalClients');
            if (data_get($data, 'resource') !== 'clients.services'
                || data_get($data, 'schemaVersion') !== 1
                || data_get($data, 'scope.status') !== $status
                || ! is_int($total) || $total < 0
                || ! is_array($data['clients'] ?? null)
                || data_get($data, 'page.returned') !== count($data['clients'])
                || ! is_string($data['snapshotVersion'] ?? null)
                || ! array_key_exists('nextCursor', $data['page'])) {
                throw new RuntimeException('Risposta Report non valida.');
            }
            $current = [$data['snapshotVersion'], $total, data_get($data, 'scope.activityYear')];
            if ($snapshot !== null && $snapshot !== $current) {
                throw new RuntimeException('Dati cambiati durante la lettura: ripetere anteprima.');
            }
            $snapshot = $current;
            foreach ($data['clients'] as $client) {
                $id = $client['id'] ?? null;
                if (! is_string($id) || $id === '' || isset($ids[$id])) {
                    throw new RuntimeException('Identificativo Report mancante o duplicato.');
                }
                $ids[$id] = true;
                $clients[] = $client;
            }
            $cursor = $data['page']['nextCursor'];
            if ($cursor === null) {
                if (count($clients) !== $total) {
                    throw new RuntimeException('Elenco Report incompleto.');
                }
                return $clients;
            }
            if (! is_string($cursor) || $cursor === '' || isset($cursors[$cursor]) || ! $data['clients']) {
                throw new RuntimeException('Paginazione Report non valida.');
            }
            $cursors[$cursor] = true;
        }
        throw new RuntimeException('Superato limite pagine Report.');
    }

    public function build(array $remote, array $local, array $links, array $services): array
    {
        $normalize = static fn ($v) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $v)));
        $rows = $areas = [];
        foreach ($remote as $client) {
            $candidates = [];
            foreach ($local as $existing) {
                $reasons = [];
                if ($normalize($client['name'] ?? '') !== '' && $normalize($client['name']) === $normalize($existing['name'])) {
                    $reasons[] = 'nome';
                }
                $vat = data_get($client, 'profile.fields.vatNumber.value');
                if ($vat && $normalize($vat) === $normalize($existing['vat_number'] ?? '')) {
                    $reasons[] = 'partita IVA';
                }
                if ($reasons) {
                    $candidates[] = ['id' => $existing['id'], 'name' => $existing['name'], 'reasons' => $reasons];
                }
            }
            $linked = $links[$client['id']] ?? null;
            $rows[] = ['external_id' => $client['id'], 'name' => $client['name'],
                'status' => $linked ? 'collegato' : ($candidates ? 'da confermare' : 'senza corrispondenza'),
                'client_id' => $linked, 'candidates' => $linked ? [] : $candidates];
            $clientAreas = $client['activeAreas'] ?? [];
            foreach ($client['services'] ?? [] as $service) {
                $clientAreas = array_merge($clientAreas, $service['areas'] ?? []);
            }
            foreach ($clientAreas as $area) {
                $excluded = in_array($normalize($area['name']), ['PEC', 'PLUGIN'], true);
                $matches = array_values(array_filter($services, fn ($s) => $normalize($s['name']) === $normalize($area['name'])));
                $areas[$area['id']] = ['external_id' => $area['id'], 'name' => $area['name'],
                    'excluded' => $excluded, 'candidates' => $excluded ? [] : $matches];
            }
        }
        return ['read_only' => true, 'clients' => $rows, 'areas' => array_values($areas),
            'summary' => array_count_values(array_column($rows, 'status'))];
    }
}
