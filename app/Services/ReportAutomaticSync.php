<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReportAutomaticSync
{
    public function plan(array $remote): array
    {
        $local = DB::table('clients')->whereNull('parent_client_id')->get();
        $links = DB::table('report_client_links')->get();
        $normalize = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $v)));
        $choices = [];
        $errors = [];
        foreach ($remote as $client) {
            $link = $links->firstWhere('external_id', $client['id']);
            if ($link) { $choices[$client['id']] = $link->client_id; continue; }
            $vat = $normalize(app(ReportSynchronizer::class)->profile($client)['vat_number'] ?? '');
            $matches = $local->filter(fn ($c) => $normalize($c->name) === $normalize($client['name'])
                || ($vat !== '' && $normalize($c->vat_number) === $vat));
            $conflictingVat = $matches->count() === 1 && $vat !== '' && $normalize($matches->first()->vat_number) !== ''
                && $normalize($matches->first()->vat_number) !== $vat;
            if ($conflictingVat || $matches->count() > 1 || ($matches->count() === 1 && $links->contains('client_id', $matches->first()->id))) {
                $errors[] = ['name' => $client['name'], 'message' => 'Corrispondenza ambigua: nessun dato modificato.'];
                continue;
            }
            $choices[$client['id']] = $matches->first()?->id ?? 'new';
        }
        // Two remote identities must never claim the same local record during bootstrap.
        $duplicates = array_keys(array_filter(array_count_values(array_filter($choices, fn ($id) => $id !== 'new')), fn ($n) => $n > 1));
        foreach ($remote as $client) {
            if (in_array($choices[$client['id']] ?? null, $duplicates, true)) {
                unset($choices[$client['id']]);
                $errors[] = ['name' => $client['name'], 'message' => 'Piu identita Report corrispondono alla stessa scheda.'];
            }
        }
        return ['choices' => $choices, 'errors' => $errors];
    }

    public function execute(string $actor): array
    {
        return Cache::lock('report-sync-execution', 900)->get(function () use ($actor) {
            if (config('report_sync.status') !== 'all') throw new RuntimeException('Richiesto elenco completo.');
            try {
                $remote = app(ReportSyncPreview::class)->fetch();
                // A suddenly empty upstream must not archive the whole customer base.
                if (! $remote) throw new RuntimeException('Elenco vuoto: sincronizzazione sospesa.');
            } catch (\Throwable $e) {
                $failure = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'archived' => 0,
                    'errors' => [['name' => 'Connessione Report', 'message' => 'Lettura completa non riuscita. Nessun cliente modificato.']]];
                DB::table('report_sync_runs')->insert(['id' => (string) Str::uuid(), 'dry_run' => false,
                    'status' => 'failed', 'error_count' => 1, 'result' => json_encode($failure),
                    'started_at' => now(), 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                throw $e;
            }
            $plan = $this->plan($remote);
            $areas = [];
            DB::transaction(function () use ($remote, &$areas) {
                foreach ($remote as $client) {
                    foreach (array_merge($client['activeAreas'] ?? [], ...array_map(fn ($s) => $s['areas'] ?? [], $client['services'] ?? [])) as $area) {
                        $name = mb_strtoupper(trim($area['name']));
                        if (in_array($name, config('report_sync.excluded_areas'), true) || isset($areas[$area['id']])) continue;
                        $id = DB::table('report_area_mappings')->where('external_id', $area['id'])->value('service_id');
                        if (! $id) {
                            $matches = DB::table('services')->get()->filter(fn ($s) => mb_strtoupper(trim($s->name)) === $name);
                            if ($matches->count() > 1) throw new RuntimeException('Servizi omonimi.');
                            $id = $matches->first()?->id;
                            if (! $id) {
                                $id = (string) Str::uuid();
                                DB::table('services')->insert(['id' => $id, 'name' => $name, 'active' => true]);
                            }
                        }
                        $areas[$area['id']] = $id;
                    }
                }
            });
            $result = app(ReportSynchronizer::class)->run($remote, $plan['choices'], $areas, $actor);
            $result['errors'] = array_merge($plan['errors'], $result['errors']);
            $result['archived'] = 0;
            // Archive only previously linked clients, after a complete successful read/run.
            if (! $result['errors']) {
                DB::transaction(function () use ($remote, &$result) {
                    $missing = DB::table('report_client_links')->whereNotIn('external_id', array_column($remote, 'id'))->pluck('client_id');
                    $result['archived'] = DB::table('clients')->whereIn('id', $missing)->where('active', true)
                        ->update(['active' => false, 'updated_at' => now()]);
                    // Force reconciliation if an unchanged record reappears later.
                    DB::table('report_client_links')->whereIn('client_id', $missing)->whereNotNull('report_revision')->update([
                        'report_revision' => null, 'report_profile_revision' => null, 'report_services_revision' => null,
                    ]);
                });
            }
            DB::table('report_sync_runs')->where('id', $result['run_id'])->update([
                'status' => $result['errors'] ? 'partial' : 'completed',
                'error_count' => count($result['errors']),
                'updated_count' => $result['updated'] + $result['archived'],
                'result' => json_encode($result),
                'updated_at' => now(),
            ]);
            return $result;
        }) ?: throw new RuntimeException('Sincronizzazione gia in corso.');
    }
}
