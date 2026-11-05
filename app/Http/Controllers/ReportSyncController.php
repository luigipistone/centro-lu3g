<?php

namespace App\Http\Controllers;

use App\Services\ReportSyncPreview;
use App\Services\ReportSettings;
use App\Services\ReportSynchronizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Throwable;

class ReportSyncController extends Controller
{
    private function authorizeSuperadmin(Request $request): void
    {
        abort_unless(DB::table('user_roles')->where('user_id', $request->user()->id)
            ->where('role', 'superadmin')->exists(), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeSuperadmin($request);

        return Inertia::render('Centro/ReportSync', ['configured' => app(ReportSettings::class)->configured()]);
    }

    public function update(Request $request, ReportSettings $settings)
    {
        $this->authorizeSuperadmin($request);
        $validator = Validator::make($request->only('token'), ['token' => ['required', 'string', 'max:4000', 'regex:/^\S+$/']]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422)
                ->header('Cache-Control', 'private, no-store');
        }
        $settings->save($validator->validated()['token']);

        return response()->json(['configured' => true])->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, ReportSyncPreview $preview)
    {
        $this->authorizeSuperadmin($request);
        try {
            $remote = $preview->fetch();
            $clients = DB::table('clients')->get(['id', 'name', 'vat_number'])->map(fn ($r) => (array) $r)->all();
            $services = DB::table('services')->get(['id', 'name'])->map(fn ($r) => (array) $r)->all();
            $links = Schema::hasTable('report_client_links')
                ? DB::table('report_client_links')->pluck('client_id', 'external_id')->all() : [];

            $snapshot = (string) Str::uuid();
            Cache::put('report-preview:'.$request->user()->id.':'.$snapshot, $remote, now()->addMinutes(15));
            return response()->json($preview->build($remote, $clients, $links, $services) + [
                'snapshot' => $snapshot, 'local_clients' => $clients, 'local_services' => $services,
                'area_mappings' => DB::table('report_area_mappings')->whereNotNull('service_id')->pluck('service_id', 'external_id'),
                'source_statuses' => collect($remote)->pluck('status', 'id'),
                'profile_changes' => collect($remote)->mapWithKeys(fn ($c) => [$c['id'] => app(ReportSynchronizer::class)->profile($c)]),
            ])
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $exception) {
            // Do not expose upstream response bodies or credentials in errors.
            return response()->json(['message' => 'Anteprima non disponibile. Verifica la credenziale Report e riprova.'], 422)
                ->header('Cache-Control', 'private, no-store');
        }
    }

    public function synchronize(Request $request, ReportSynchronizer $sync)
    {
        $this->authorizeSuperadmin($request);
        $validator = Validator::make($request->all(), [
            'snapshot' => ['required', 'uuid'], 'clients' => ['required', 'array', 'min:1'],
            'clients.*' => ['required', 'string', 'max:100'], 'areas' => ['present', 'array'],
            'areas.*' => ['required', 'uuid', 'exists:services,id'],
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Selezioni non valide.', 'errors' => $validator->errors()], 422);
        $lock = Cache::lock('report-sync-execution', 600);
        if (! $lock->get()) return response()->json(['message' => 'Sincronizzazione gia in corso.'], 409);
        try {
            $key = 'report-preview:'.$request->user()->id.':'.$request->input('snapshot');
            $remote = Cache::get($key);
            if (! is_array($remote)) return response()->json(['message' => 'Anteprima scaduta. Aggiornala prima di procedere.'], 409);
            if (array_diff(array_keys($request->input('clients')), array_column($remote, 'id'))) {
                return response()->json(['message' => 'Cliente non presente nell’anteprima.'], 422);
            }
            // Execute exactly the reviewed snapshot, never silently replace it with new data.
            $result = $sync->run($remote, $request->input('clients'), $request->input('areas'), $request->user()->id);
            Cache::forget($key);
            return response()->json($result)->header('Cache-Control', 'private, no-store');
        } catch (Throwable $e) {
            return response()->json(['message' => 'Sincronizzazione interrotta. Aggiorna l’anteprima e verifica le associazioni.'], 422);
        } finally {
            $lock->release();
        }
    }
}
