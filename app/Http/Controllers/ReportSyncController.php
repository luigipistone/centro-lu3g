<?php

namespace App\Http\Controllers;

use App\Services\ReportSyncPreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        return Inertia::render('Centro/ReportSync', ['configured' => filled(config('report_sync.token'))]);
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
