<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShortLinkController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Centro/ShortLinks', [
            'links' => $this->visibleLinks($request),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'string', 'max:2048', 'url', function ($attribute, $value, $fail) {
                $parts = parse_url(trim($value));
                $host = strtolower($parts['host'] ?? '');
                if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                    || ! $host || isset($parts['user']) || isset($parts['pass'])
                    || in_array($host, ['localhost', 'localhost.localdomain'], true)
                    || (filter_var($host, FILTER_VALIDATE_IP)
                        && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                    $fail('Inserisci un link web pubblico valido.');
                }
            }],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->toArray()], 422);
        }
        $data = $validator->validated();
        $url = trim($data['url']);

        $id = (string) Str::uuid();
        do {
            $code = Str::random(8);
        } while (DB::table('short_links')->where('code', $code)->exists());

        DB::table('short_links')->insert([
            'id' => $id,
            'user_id' => $request->user()->id,
            'code' => $code,
            'destination_url' => $url,
            'clicks' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['link' => $this->formatLink(DB::table('short_links')->where('id', $id)->first())], 201);
    }

    public function destroy(Request $request, string $id): \Illuminate\Http\Response
    {
        $link = DB::table('short_links')->where('id', $id)->first();
        abort_unless($link, 404);
        abort_unless($link->user_id === $request->user()->id || $this->isSuperadmin($request), 403);
        DB::table('short_links')->where('id', $id)->delete();

        return response()->noContent();
    }

    public function redirect(string $code): RedirectResponse
    {
        $link = DB::table('short_links')->where('code', $code)->first();
        abort_unless($link, 404);
        DB::table('short_links')->where('id', $link->id)->update([
            'clicks' => DB::raw('clicks + 1'),
            'last_clicked_at' => now(),
        ]);

        return redirect()->away($link->destination_url, 302)->header('Cache-Control', 'no-store');
    }

    private function visibleLinks(Request $request): array
    {
        return DB::table('short_links')
            ->when(! $this->isSuperadmin($request), fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($link) => $this->formatLink($link))
            ->all();
    }

    private function formatLink(object $link): array
    {
        return [
            'id' => $link->id,
            'user_id' => $link->user_id,
            'url' => $link->destination_url,
            'short_url' => route('short-links.redirect', $link->code),
            'clicks' => $link->clicks,
            'created_at' => $link->created_at,
        ];
    }

    private function isSuperadmin(Request $request): bool
    {
        return DB::table('user_roles')->where('user_id', $request->user()->id)->where('role', 'superadmin')->exists();
    }
}
