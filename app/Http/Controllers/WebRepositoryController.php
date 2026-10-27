<?php

namespace App\Http\Controllers;

use App\Services\WebRepositoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WebRepositoryController extends Controller
{
    public function tools(Request $request): Response
    {
        return Inertia::render('Centro/ToolsIndex', [
            'showWebRepositories' => $this->canView($request),
        ]);
    }

    public function index(Request $request, WebRepositoryService $github): Response
    {
        abort_unless($this->canView($request), 403);

        $repositories = [];
        $error = null;
        if ($github->isConfigured()) {
            try {
                $repositories = $github->repositories($request->boolean('refresh'));
            } catch (Throwable $exception) {
                report($exception);
                $error = 'Impossibile caricare le repository. Controlla il token GitHub nelle Impostazioni.';
            }
        }

        return Inertia::render('Centro/WebRepositories', [
            'repositories' => $repositories,
            'configured' => $github->isConfigured(),
            'error' => $error,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->role($request) === 'superadmin', 403);

        $payload = $request->validate(['token' => ['required', 'string', 'max:4000']]);
        DB::table('github_settings')->updateOrInsert(
            ['id' => DB::table('github_settings')->value('id') ?? (string) Str::uuid()],
            ['encrypted_token' => Crypt::encryptString(trim($payload['token'])), 'updated_at' => now(), 'created_at' => now()],
        );
        Cache::forget('web-repositories:lu3gofficial');

        return back()->with('status', 'Connessione GitHub salvata.');
    }

    public function download(Request $request, WebRepositoryService $github, string $repository): BinaryFileResponse|RedirectResponse
    {
        abort_unless($this->canView($request), 403);

        try {
            $path = $github->downloadArchive($repository);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            abort(404);
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('tools.web-repositories')
                ->withErrors(['github_download' => $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'Download non riuscito. Riprova tra poco.']);
        }

        $response = response()->download($path, $repository.'.zip')->deleteFileAfterSend(true);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function test(Request $request, WebRepositoryService $github): RedirectResponse
    {
        abort_unless($this->role($request) === 'superadmin', 403);

        try {
            $count = count($github->repositories(true));
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['github_test' => 'Connessione non riuscita. Controlla token e permessi sulle repository dell’organizzazione.']);
        }

        return back()->with('status', "Connessione GitHub riuscita: {$count} repository corrispondenti.");
    }

    private function canView(Request $request): bool
    {
        $role = $this->role($request);
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return true;
        }

        return $role === 'editor'
            && DB::table('profiles')->where('user_id', $request->user()->id)->value('team_division') === 'web';
    }

    private function role(Request $request): string
    {
        return DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?? 'guest';
    }
}
