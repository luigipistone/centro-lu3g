<?php

namespace App\Http\Controllers;

use App\Services\ReportSyncPreview;
use App\Services\ReportSettings;
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

            return response()->json($preview->build($remote, $clients, $links, $services))
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $exception) {
            // Do not expose upstream response bodies or credentials in errors.
            return response()->json(['message' => 'Anteprima non disponibile. Verifica la credenziale Report e riprova.'], 422)
                ->header('Cache-Control', 'private, no-store');
        }
    }
}
