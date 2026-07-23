<?php

namespace App\Http\Controllers;

use App\Services\FigmaService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class FigmaIntegrationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $payload = $request->validate([
            'team_id' => ['required', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:4000'],
        ]);
        $existing = DB::table('figma_settings')->first();

        if (! $existing && blank($payload['token'])) {
            throw ValidationException::withMessages(['token' => 'Inserisci il token personale Figma.']);
        }

        $values = [
            'team_id' => trim($payload['team_id']),
            'updated_at' => now(),
            'created_at' => $existing->created_at ?? now(),
        ];
        if (filled($payload['token'])) {
            $values['encrypted_token'] = Crypt::encryptString(trim($payload['token']));
            $values['token_expires_at'] = now('Europe/Rome')->addDays(90)->toDateString();
        }

        DB::table('figma_settings')->updateOrInsert(
            ['id' => $existing->id ?? (string) str()->uuid()],
            $values,
        );

        return back()->with('status', 'Connessione Figma salvata.');
    }

    public function test(Request $request, FigmaService $figma): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        try {
            $count = count($figma->projects());
        } catch (Throwable $exception) {
            return back()->withErrors(['figma_test' => $this->friendlyError($exception)]);
        }

        return back()->with('status', "Connessione Figma riuscita: {$count} progetti trovati.");
    }

    public function projects(Request $request, FigmaService $figma): JsonResponse
    {
        $this->ensureAdmin($request);

        try {
            return response()->json(['projects' => $figma->projects()]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $this->friendlyError($exception)], 422);
        }
    }

    public function files(Request $request, string $projectId, FigmaService $figma): JsonResponse
    {
        $this->ensureAdmin($request);

        try {
            return response()->json(['files' => $figma->files($projectId)]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $this->friendlyError($exception)], 422);
        }
    }

    private function ensureSuperadmin(Request $request): void
    {
        abort_unless($this->role($request) === 'superadmin', 403);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(in_array($this->role($request), ['admin', 'superadmin'], true), 403);
    }

    private function role(Request $request): ?string
    {
        return DB::table('user_roles')->where('user_id', $request->user()->id)->value('role');
    }

    private function friendlyError(Throwable $exception): string
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return match ($status) {
            401, 403 => 'Token Figma non valido, scaduto o privo del permesso projects:read.',
            404 => 'Team o progetto Figma non trovato.',
            429 => 'Figma ha temporaneamente limitato le richieste. Riprova tra poco.',
            default => $exception->getMessage(),
        };
    }
}
