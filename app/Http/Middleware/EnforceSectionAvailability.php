<?php

namespace App\Http\Middleware;

use App\Services\SectionAvailabilityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnforceSectionAvailability
{
    public function __construct(private readonly SectionAvailabilityService $availability) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return $next($request);
        }

        $section = $this->availability->sectionForRoute((string) $request->route()?->getName());
        if ($section === null) {
            return $next($request);
        }

        $role = DB::table('user_roles')->where('user_id', $request->user()->id)->value('role');
        if (! in_array($role, ['superadmin', 'admin'], true) && ! $this->availability->statuses()[$section]) {
            abort(503, 'Questa sezione è temporaneamente non disponibile.');
        }

        return $next($request);
    }
}
