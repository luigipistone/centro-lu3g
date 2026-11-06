<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ReportSynchronizer
{
    public function profile(array $client): array
    {
        $result = ['name' => mb_strtoupper($client['name'])];
        $value = static function (string $key) use ($client) {
            $field = data_get($client, 'profile.fields.'.$key);
            return ($field['availability'] ?? null) === 'available' ? ($field['value'] ?? null) : null;
        };
        if (data_get($client, 'source.type') === 'company') {
            $result['legal_name'] = $value('name');
        }
        foreach (['vat_number' => 'vatNumber', 'website' => 'website', 'electronic_invoicing_address' => 'electronicInvoicingAddress'] as $local => $remote) {
            $result[$local] = $value($remote);
        }
        foreach (['email' => ['emails', 'email'], 'phone' => ['telephones', 'number']] as $local => [$key, $part]) {
            $list = collect($value($key) ?? []);
            $chosen = $list->firstWhere('type', 'primary') ?? $list->first();
            $result[$local] = $chosen[$part] ?? null;
        }
        $index = data_get($client, 'profile.normalized.primaryAddress.index');
        $addresses = $value('addresses') ?? [];
        if (is_int($index) && isset($addresses[$index])) {
            $address = $addresses[$index]['address'] ?? [];
            foreach (['street' => 'line_1', 'postal_code' => 'postal_code', 'city' => 'city', 'country' => 'country'] as $local => $remote) {
                $result[$local] = $address[$remote] ?? null;
            }
            foreach (data_get($client, 'profile.normalized.addressRegions', []) as $region) {
                if (($region['index'] ?? null) === $index && data_get($region, 'region.availability') === 'available') {
                    $result['province'] = data_get($region, 'region.value.name');
                }
            }
        }
        if (data_get($client, 'profile.normalized.legalType.availability') === 'available') {
            $result['legal_form'] = data_get($client, 'profile.normalized.legalType.value.name');
        }
        return array_filter($result, fn ($v) => is_string($v) && trim($v) !== '');
    }

    public function run(array $clients, array $decisions, array $areaChoices, string $actor): array
    {
        $knownAreas = [];
        foreach ($clients as $client) {
            foreach (array_merge($client['activeAreas'] ?? [], ...array_map(fn ($s) => $s['areas'] ?? [], $client['services'] ?? [])) as $area) {
                $knownAreas[$area['id']] = $area;
            }
        }
        // Validate every category before making any client changes.
        foreach ($areaChoices as $id => $serviceId) {
            if (! isset($knownAreas[$id]) || ! DB::table('services')->where('id', $serviceId)->exists()) {
                throw new RuntimeException('Associazione categoria non valida.');
            }
            $existing = DB::table('report_area_mappings')->where('external_id', $id)->value('service_id');
            if ($existing && $existing !== $serviceId) throw new RuntimeException('Categoria gia collegata a un altro servizio.');
        }
        $runId = (string) Str::uuid();
        DB::transaction(function () use ($knownAreas, $areaChoices, $runId) {
            foreach ($areaChoices as $id => $serviceId) {
                $area = $knownAreas[$id];
                if (in_array(mb_strtoupper(trim($area['name'])), config('report_sync.excluded_areas'), true)) continue;
                DB::table('report_area_mappings')->upsert([['id' => (string) Str::uuid(), 'external_id' => $id,
                    'name' => $area['name'], 'service_id' => $serviceId, 'excluded' => false, 'created_at' => now(), 'updated_at' => now()]],
                    ['external_id'], ['name', 'service_id', 'excluded', 'updated_at']);
            }
            DB::table('report_sync_runs')->insert(['id' => $runId, 'dry_run' => false, 'status' => 'running', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        });
        $mappings = DB::table('report_area_mappings')->where('excluded', false)->whereNotNull('service_id')->orderBy('external_id')->pluck('service_id', 'external_id')->all();
        $mappingRevision = hash('sha256', json_encode($mappings));
        $result = ['run_id' => $runId, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => []];
        foreach ($clients as $client) {
            $choice = $decisions[$client['id']] ?? null;
            if (! $choice) { $result['skipped']++; continue; }
            try {
                $outcome = DB::transaction(fn () => $this->syncClient($client, $choice, $mappings, $mappingRevision, $actor));
                $result[$outcome]++;
            } catch (Throwable $e) {
                $result['errors'][] = ['name' => $client['name'], 'message' => 'Aggiornamento non eseguito. Verifica associazione e categorie; dati precedenti conservati.'];
            }
        }
        DB::table('report_sync_runs')->where('id', $runId)->update(['status' => $result['errors'] ? 'partial' : 'completed',
            'created_count' => $result['created'], 'updated_count' => $result['updated'], 'unchanged_count' => $result['unchanged'],
            'error_count' => count($result['errors']), 'finished_at' => now(), 'updated_at' => now()]);
        return $result;
    }

    private function syncClient(array $client, string $choice, array $mappings, string $mappingRevision, string $actor): string
    {
        if (! in_array($client['status'] ?? null, ['active', 'inactive'], true) || ! filled($client['revision'] ?? null)) {
            throw new RuntimeException('Stato o revisione non validi.');
        }
        if (! is_string($client['name'] ?? null) || trim($client['name']) === ''
            || ! is_array($client['activeAreas'] ?? null) || ! is_array($client['services'] ?? null)) {
            throw new RuntimeException('Scheda Report incompleta.');
        }
        $link = DB::table('report_client_links')->where('external_id', $client['id'])->lockForUpdate()->first();
        $new = false;
        if ($link && $choice !== $link->client_id && $choice !== 'new') throw new RuntimeException('Collegamento diverso.');
        if (! $link) {
            $localId = $choice;
            if ($choice === 'new') {
                $new = true;
                $localId = (string) Str::uuid();
                DB::table('clients')->insert(['id' => $localId, 'name' => mb_strtoupper($client['name']), 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            } elseif (! DB::table('clients')->where('id', $localId)->exists()) throw new RuntimeException('Cliente inesistente.');
            $linkId = (string) Str::uuid();
            DB::table('report_client_links')->insert(['id' => $linkId, 'client_id' => $localId, 'external_id' => $client['id'],
                'teamleader_id' => data_get($client, 'source.id'), 'source_type' => data_get($client, 'source.type'), 'created_at' => now(), 'updated_at' => now()]);
            $link = DB::table('report_client_links')->where('id', $linkId)->first();
        }
        $split = filled($client['profileRevision'] ?? null) && filled($client['servicesRevision'] ?? null);
        $profileChanged = ($split ? $link->report_profile_revision !== $client['profileRevision'] : $link->report_revision !== $client['revision'])
            || (int) $link->profile_mapping_version !== 2;
        $servicesChanged = ($split ? $link->report_services_revision !== $client['servicesRevision'] : $link->report_revision !== $client['revision'])
            || $link->mapping_revision !== $mappingRevision;
        if (! $profileChanged && ! $servicesChanged) return 'unchanged';
        $values = $profileChanged ? $this->profile($client) : [];
        foreach ($values as $value) {
            if (mb_strlen($value) > 255) throw new RuntimeException('Campo troppo lungo.');
        }
        if ($servicesChanged) {
            $values['active'] = $client['status'] === 'active';
            $desired = [];
            foreach ($client['activeAreas'] ?? [] as $area) {
                if (in_array(mb_strtoupper(trim($area['name'])), config('report_sync.excluded_areas'), true)) continue;
                if (! isset($mappings[$area['id']])) throw new RuntimeException('Categoria non associata.');
                $desired[] = $mappings[$area['id']];
            }
            foreach ($client['services'] ?? [] as $service) {
                DB::table('report_client_services')->upsert([['id' => (string) Str::uuid(), 'report_client_link_id' => $link->id,
                    'external_id' => $service['id'], 'name' => $service['name'], 'status' => $service['status'],
                    'scheduled' => $service['scheduled'] ?? false, 'unverified' => $service['unverified'] ?? false,
                    'areas' => json_encode($service['areas'] ?? []), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]],
                    ['report_client_link_id', 'external_id'], ['name', 'status', 'scheduled', 'unverified', 'areas', 'last_seen_at', 'updated_at']);
            }
            foreach (array_unique($desired) as $serviceId) {
                DB::table('client_services')->upsert([['id' => (string) Str::uuid(), 'client_id' => $link->client_id,
                    'service_id' => $serviceId, 'manual_association' => false, 'report_association' => true]],
                    ['client_id', 'service_id'], ['report_association']);
            }
            // Missing services are not evidence of termination, even in a complete list.
            if (($client['servicesComplete'] ?? false) === true) {
                $inactiveAreas = collect($client['services'] ?? [])->where('status', 'inactive')->flatMap(fn ($s) => $s['areas'] ?? [])->pluck('id');
                $otherAreas = collect($client['services'] ?? [])->where('status', '!=', 'inactive')->flatMap(fn ($s) => $s['areas'] ?? [])->pluck('id');
                $knownLocal = collect($mappings)->only($inactiveAreas->diff($otherAreas)->all())->values()->all();
                DB::table('client_services')->where('client_id', $link->client_id)->whereIn('service_id', $knownLocal)
                    ->whereNotIn('service_id', $desired)->update(['report_association' => false]);
                DB::table('client_services')->where('client_id', $link->client_id)->where('manual_association', false)->where('report_association', false)->delete();
            }
        }
        if ($values) DB::table('clients')->where('id', $link->client_id)->update($values + ['updated_at' => now()]);
        DB::table('report_client_links')->where('id', $link->id)->update([
            'report_revision' => $client['revision'], 'report_profile_revision' => $client['profileRevision'] ?? null,
            'report_services_revision' => $client['servicesRevision'] ?? null, 'mapping_revision' => $mappingRevision,
            'profile_mapping_version' => 2,
            'native_updated_at' => data_get($client, 'profile.source.nativeUpdatedAt') ? \Carbon\Carbon::parse(data_get($client, 'profile.source.nativeUpdatedAt'))->utc()->toDateTimeString() : $link->native_updated_at,
            'last_synced_at' => now(), 'updated_at' => now(),
        ]);
        return $new ? 'created' : 'updated';
    }
}
