<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionWordPress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WordPressProvisioningController extends Controller
{
    public function store(Request $request, string $projectId): RedirectResponse
    {
        $this->ensureAdmin($request);
        $project = $this->projectWithClient($projectId);

        if (! $project->client_id || blank($project->client_name)) {
            throw ValidationException::withMessages([
                'project' => 'Collega un cliente al progetto prima di preparare WordPress.',
            ]);
        }

        $folder = Str::slug($project->client_name);
        if ($folder === '') {
            throw ValidationException::withMessages([
                'project' => 'Il nome del cliente non consente di generare una cartella valida.',
            ]);
        }

        $existing = DB::table('wordpress_provisionings')->where('project_id', $projectId)->first();
        if ($existing && in_array($existing->status, ['queued', 'running'], true)) {
            return back()->with('status', 'Il provisioning WordPress è già in corso.');
        }
        if ($existing && $existing->status === 'completed') {
            return back()->with('status', 'WordPress è già stato preparato per questo progetto.');
        }

        $id = $existing?->id ?: (string) Str::uuid();
        $payload = [
            'client_id' => $project->client_id,
            'started_by' => $request->user()->id,
            'folder_slug' => $folder,
            'site_url' => rtrim((string) config('wordpress-provisioning.base_url'), '/').'/'.$folder,
            'status' => 'queued',
            'current_step' => 'queued',
            'progress' => 0,
            'error_message' => null,
            'completed_at' => null,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('wordpress_provisionings')->where('id', $id)->update($payload);
        } else {
            DB::table('wordpress_provisionings')->insert([
                'id' => $id,
                'project_id' => $projectId,
                ...$payload,
                'created_at' => now(),
            ]);
        }

        ProvisionWordPress::dispatch($id);

        return back()->with('status', 'Preparazione WordPress avviata.');
    }

    public function status(Request $request, string $projectId): JsonResponse
    {
        $this->ensureAdmin($request);
        $this->projectWithClient($projectId);

        return response()->json([
            'provisioning' => $this->publicRow(
                DB::table('wordpress_provisionings')->where('project_id', $projectId)->first()
            ),
        ]);
    }

    private function projectWithClient(string $projectId): object
    {
        $project = DB::table('projects')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->where('projects.id', $projectId)
            ->first(['projects.id', 'projects.client_id', 'clients.name as client_name']);

        abort_if(! $project, 404);

        return $project;
    }

    private function ensureAdmin(Request $request): void
    {
        $role = DB::table('user_roles')->where('user_id', $request->user()->id)->value('role');
        abort_unless(in_array($role, ['admin', 'superadmin'], true), 403);
    }

    private function publicRow(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        return [
            'id' => $row->id,
            'folder_slug' => $row->folder_slug,
            'site_url' => $row->site_url,
            'status' => $row->status,
            'current_step' => $row->current_step,
            'progress' => (int) $row->progress,
            'database_name' => $row->database_name,
            'credential_item_id' => $row->credential_item_id,
            'credential_title' => $row->folder_slug.' WordPress',
            'log' => $row->log,
            'error_message' => $row->error_message,
            'started_at' => $row->started_at,
            'completed_at' => $row->completed_at,
        ];
    }
}
