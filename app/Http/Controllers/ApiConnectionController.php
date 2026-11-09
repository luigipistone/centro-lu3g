<?php

namespace App\Http\Controllers;

use App\Models\ApiConnection;
use App\Services\CommonApiAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApiConnectionController extends Controller
{
    private function authorizeAdmin(Request $r): void
    {
        abort_unless(DB::table('user_roles')->where('user_id', $r->user()->id)->where('role', 'superadmin')->exists(), 403);
    }

    public function index(Request $r)
    {
        $this->authorizeAdmin($r);

        return response()->json(['connections' => ApiConnection::orderByDesc('created_at')->get(),
            'clients' => DB::table('clients')->orderBy('name')->get(['id', 'name', 'active']),
            'projects' => DB::table('projects')->orderBy('name')->get(['id', 'name', 'client_id', 'status']),
            'scopes' => IntegrationApiController::SCOPES,
            'general_scopes' => CommonApiAccess::SCOPES,
            'users' => DB::table('users')->where('account_status', 'active')->orderBy('name')->get(['id', 'name']),
            'webhooks' => DB::table('api_webhooks')->get(['id', 'connection_id', 'task_id', 'url', 'active'])->concat(DB::table('api_subscriptions')->get(['id', 'connection_id', 'url', 'active'])),
            'deliveries' => DB::table('api_webhook_deliveries')->orderByDesc('created_at')->limit(50)->get(['id', 'webhook_id', 'status', 'attempts', 'http_status', 'updated_at'])
                ->concat(DB::table('api_events')->orderByDesc('created_at')->limit(50)->get(['id', 'subscription_id as webhook_id', 'status', 'attempts', 'http_status', 'updated_at']))->sortByDesc('updated_at')->take(50)->values(),
            'logs' => DB::table('api_request_logs')->orderByDesc('id')->limit(50)->get()]);
    }

    public function store(Request $r)
    {
        $this->authorizeAdmin($r);
        if ($r->input('kind') === 'general') {
            return $this->storeGeneral($r);
        }
        $p = $r->validate(['name' => 'required|string|max:120', 'site' => 'required|url:https|max:500', 'client_id' => 'required|uuid|exists:clients,id',
            'project_id' => ['required', 'uuid', Rule::exists('projects', 'id')->where('client_id', $r->input('client_id'))->whereNot('status', 'archived')],
            'scopes' => 'required|array|min:1', 'scopes.*' => [Rule::in(IntegrationApiController::SCOPES)], 'expires_at' => 'required|date|after:today|before_or_equal:'.now()->addYear()->toDateString()]);
        $parts = parse_url($p['site']);
        abort_if(isset($parts['user']) || isset($parts['pass']) || ($parts['port'] ?? 443) !== 443 || filter_var($parts['host'], FILTER_VALIDATE_IP), 422);
        abort_unless(DB::table('clients')->where('id', $p['client_id'])->where('active', true)->exists(), 422);
        $p['site'] = 'https://'.strtolower($parts['host']);
        $result = DB::transaction(function () use ($p, $r) {
            $c = ApiConnection::create([...$p, 'created_by' => $r->user()->id]);

            return ['connection' => $c, 'token' => $c->createToken($c->name, $c->scopes, $c->expires_at)->plainTextToken];
        });

        return response()->json($result, 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $r, string $id)
    {
        $this->authorizeAdmin($r);
        $c = ApiConnection::findOrFail($id);
        $p = $r->validate(['scopes' => 'required|array', 'scopes.*' => [Rule::in($c->kind === 'general' ? CommonApiAccess::SCOPES : IntegrationApiController::SCOPES)]]);
        if ($c->kind === 'general' && $r->has('access_policy')) {
            $p['access_policy'] = $this->policy($r);
        }
        DB::transaction(function () use ($c, $p) {
            $c->update($p);
            $c->tokens()->update(['abilities' => json_encode($p['scopes'])]);
        });

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $r, string $id)
    {
        $this->authorizeAdmin($r);
        $c = ApiConnection::findOrFail($id);
        DB::transaction(function () use ($c) {
            $c->update(['revoked_at' => now()]);
            $c->tokens()->delete();
        });

        return response()->json(['revoked' => true]);
    }

    private function policy(Request $r): array
    {
        $rules = ['access_policy' => 'required|array:clients,projects,users'];
        foreach (['clients', 'projects', 'users'] as $resource) {
            $rules["access_policy.$resource"] = 'required|array:all,ids';
            $rules["access_policy.$resource.all"] = 'required|boolean';
            $rules["access_policy.$resource.ids"] = 'present|array';
            $rules["access_policy.$resource.ids.*"] = ['uuid', 'distinct', Rule::exists($resource, 'id')];
        }
        $policy = $r->validate($rules)['access_policy'];
        foreach ($policy as &$entry) {
            $entry['all'] = (bool) $entry['all'];
        }

        return $policy;
    }

    private function storeGeneral(Request $r)
    {
        $p = $r->validate(['name' => 'required|string|max:120', 'site' => 'required|url:https|max:500',
            'scopes' => 'required|array|min:1', 'scopes.*' => [Rule::in(CommonApiAccess::SCOPES)],
            'expires_at' => 'required|date|after:today|before_or_equal:'.now()->addYear()->toDateString()]);
        $parts = parse_url($p['site']);
        abort_if(isset($parts['user']) || isset($parts['pass']) || ($parts['port'] ?? 443) !== 443 || filter_var($parts['host'], FILTER_VALIDATE_IP), 422);
        $p['site'] = 'https://'.strtolower($parts['host']);
        $p['access_policy'] = $this->policy($r);

        return DB::transaction(function () use ($p, $r) {
            $c = ApiConnection::create([...$p, 'kind' => 'general', 'created_by' => $r->user()->id]);

            return response()->json(['connection' => $c, 'token' => $c->createToken($c->name, $c->scopes, $c->expires_at)->plainTextToken], 201)->header('Cache-Control', 'no-store');
        });
    }

    public function retry(Request $r, string $id)
    {
        $this->authorizeAdmin($r);
        $table = DB::table('api_events')->where('id', $id)->exists() ? 'api_events' : 'api_webhook_deliveries';
        $d = DB::table($table)->where('id', $id)->where('status', 'failed')->first();
        abort_unless($d, 404);
        DB::table($table)->where('id', $id)->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'updated_at' => now()]);

        return response()->json(['queued' => true]);
    }

    public function documentation(Request $r)
    {
        $this->authorizeAdmin($r);
        $markdown = file_get_contents(base_path('docs/integration-api.md'));

        return response()->json(['markdown' => $markdown, 'html' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false])]);
    }

    public function openapi(Request $r)
    {
        $this->authorizeAdmin($r);

        return response()->file(base_path('docs/integration-openapi.json'), ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="centro-openapi-v1.json"']);
    }
}
