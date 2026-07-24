<?php

namespace App\Http\Controllers;

use App\Services\FigmaService;
use App\Services\FigmaDesignSystemService;
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

    public function designSystem(Request $request, string $projectId, FigmaDesignSystemService $designSystem): JsonResponse
    {
        $this->ensureAdmin($request);
        $this->project($projectId);

        return response()->json(['design_system' => $designSystem->current($projectId)]);
    }

    public function analyzeDesignSystem(Request $request, string $projectId, FigmaDesignSystemService $designSystem): JsonResponse
    {
        $this->ensureAdmin($request);
        $project = $this->project($projectId);

        if (blank($project->figma_file_key)) {
            return response()->json(['message' => 'Collega prima un file Figma al progetto.'], 422);
        }

        try {
            return response()->json([
                'design_system' => $designSystem->analyze($project, $request->user()->id),
            ]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $this->friendlyError($exception)], 422);
        }
    }

    public function applyDesignSystem(Request $request, string $projectId, FigmaDesignSystemService $designSystem): JsonResponse
    {
        $this->ensureAdmin($request);
        $project = $this->project($projectId);
        $payload = $request->validate([
            'colors' => ['required', 'array'],
            'colors.primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.secondary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'typography' => ['required', 'array'],
            'typography.primary.family' => ['required', 'string', 'max:120'],
            'typography.primary.weight' => ['required', 'integer', 'between:100,900'],
            'typography.secondary.family' => ['required', 'string', 'max:120'],
            'typography.secondary.weight' => ['required', 'integer', 'between:100,900'],
            'typography.text.family' => ['required', 'string', 'max:120'],
            'typography.text.weight' => ['required', 'integer', 'between:100,900'],
            'typography.accent.family' => ['required', 'string', 'max:120'],
            'typography.accent.weight' => ['required', 'integer', 'between:100,900'],
        ]);
        $provisioning = DB::table('wordpress_provisionings')
            ->where('project_id', $projectId)
            ->where('status', 'completed')
            ->first();

        if (! $provisioning) {
            return response()->json(['message' => 'Completa prima la preparazione WordPress del progetto.'], 422);
        }
        if (! $designSystem->current($projectId)) {
            return response()->json(['message' => 'Analizza prima il design system Figma.'], 422);
        }

        try {
            return response()->json([
                'design_system' => $designSystem->apply(
                    $project,
                    $provisioning,
                    $payload['colors'],
                    $payload['typography'],
                    $request->user()->id,
                ),
                'message' => 'Design system applicato a Elementor. Ricarica l’editor Elementor se era già aperto.',
            ]);
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

    private function project(string $projectId): object
    {
        $project = DB::table('projects')->where('id', $projectId)->first([
            'id',
            'figma_file_key',
            'figma_file_name',
        ]);
        abort_if(! $project, 404);

        return $project;
    }

    private function friendlyError(Throwable $exception): string
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return match ($status) {
            401, 403 => 'Token Figma non valido, scaduto o privo dei permessi projects:read e file_content:read.',
            404 => 'Team o progetto Figma non trovato.',
            429 => 'Figma ha temporaneamente limitato le richieste. Riprova tra poco.',
            default => $exception->getMessage(),
        };
    }
}
