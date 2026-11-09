<?php

namespace App\Services;

use App\Models\ApiConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommonApiAccess
{
    public const SCOPES = ['clients:read', 'projects:read', 'tasks:read', 'users:read', 'absences:read', 'availability:read', 'services:read', 'webhooks:write'];

    public function connection(Request $r, string $scope): ApiConnection
    {
        $c = $r->attributes->get('api_connection');
        abort_unless($c instanceof ApiConnection && $c->kind === 'general', 403);
        abort_unless(in_array($scope, $c->scopes, true) && $r->attributes->get('api_token')->can($scope), 403);

        return $c;
    }

    public function constrain($query, ApiConnection $c, string $resource, string $column)
    {
        $policy = $c->access_policy[$resource] ?? [];
        if (($policy['all'] ?? false) !== true) {
            $query->whereIn($column, $policy['ids'] ?? []);
        }

        return $query;
    }

    public function allows(ApiConnection $c, string $resource, ?string $id): bool
    {
        $policy = $c->access_policy[$resource] ?? [];

        return ($policy['all'] ?? false) === true || ($id && in_array($id, $policy['ids'] ?? [], true));
    }

    public function ownerEnabled(ApiConnection $c): bool
    {
        return $c->enabled() && DB::table('users')->where('id', $c->created_by)->where('account_status', 'active')->exists()
            && DB::table('user_roles')->where('user_id', $c->created_by)->where('role', 'superadmin')->exists();
    }

    public function contextAllowed(ApiConnection $c, array $context): bool
    {
        foreach ($context as $resource => $id) {
            if (! $this->allows($c, $resource, $id)) {
                return false;
            }
        }

        return true;
    }
}
