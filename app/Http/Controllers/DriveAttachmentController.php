<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveStorage;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DriveAttachmentController extends Controller
{
    private function scope(Request $request): string
    {
        return str_starts_with($request->route()->getName(), 'tasks.') ? 'tasks' : 'projects';
    }

    private function access(Request $request, string $id, bool $write = false): string
    {
        $scope = $this->scope($request);
        $entity = DB::table($scope)->where('id', $id)->first();
        abort_unless($entity, 404);
        $role = DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?: 'guest';
        abort_unless(app(RolePermissionService::class)->allows($role, $scope.'.view'), 403);
        if ($write) abort_unless($role !== 'guest' && app(RolePermissionService::class)->allows($role, $scope.'.update'), 403);
        if ($role === 'guest') {
            if ($scope === 'projects') {
                $allowed = DB::table('project_followers')->where('project_id', $id)->where('user_id', $request->user()->id)->exists();
            } else {
                $ids = array_filter([$id, $entity->parent_task_id]);
                $allowed = DB::table('task_assignees')->whereIn('task_id', $ids)->where('user_id', $request->user()->id)->exists()
                    || DB::table('task_followers')->whereIn('task_id', $ids)->where('user_id', $request->user()->id)->exists();
            }
            abort_unless($allowed, 403);
        }

        return $scope === 'tasks' ? 'task_id' : 'project_id';
    }

    private function drive(callable $action)
    {
        try {
            return $action();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Never send upstream responses or credentials to the browser/logs.
            \Illuminate\Support\Facades\Log::warning('Drive attachment operation failed', ['type' => get_class($exception)]);
            throw ValidationException::withMessages(['drive' => 'Google Drive non disponibile. Riprova tra poco o verifica il collegamento.']);
        }
    }

    public function index(Request $request, string $id, GoogleDriveStorage $drive)
    {
        $key = $this->access($request, $id);
        $role = DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?: 'guest';

        return response()->json([
            'automatic_path' => ($name = $this->clientName($request, $id)) ? 'WORK / '.now('Europe/Rome')->format('Y').' / '.mb_strtoupper(trim($name)) : null,
            'configured' => $drive->configured(), 'root' => $drive->root(),
            'can_upload' => $role !== 'guest' && app(RolePermissionService::class)->allows($role, $this->scope($request).'.update'),
            'can_remove' => $role !== 'guest' && app(RolePermissionService::class)->allows($role, $this->scope($request).'.delete'),
            'files' => DB::table('drive_attachments')->where($key, $id)->latest()->get(['id', 'name', 'size', 'folder_name', 'created_at']),
        ]);
    }

    public function folders(Request $request, string $id, GoogleDriveStorage $drive)
    {
        $this->access($request, $id, true);
        $data = $request->validate(['parent' => ['nullable', 'string', 'max:255'], 'cursor' => ['nullable', 'string', 'max:2048']]);

        return response()->json($this->drive(fn () => $drive->folders($data['parent'] ?? $drive->root(), $data['cursor'] ?? null)));
    }

    public function store(Request $request, string $id, GoogleDriveStorage $drive)
    {
        $key = $this->access($request, $id, true);
        $data = $request->validate(['file' => ['required', 'file', 'max:20480'], 'folder' => ['nullable', 'string', 'max:255']]);
        if (! empty($data['folder'])) {
            $folder = $this->drive(fn () => $drive->folder($data['folder']));
        } else {
            $name = $this->clientName($request, $id);
            if (! $name || trim($name) === '') throw ValidationException::withMessages(['folder' => 'Nessun cliente associato. Seleziona una cartella Drive oppure associa un cliente.']);
            $folder = $this->drive(fn () => $drive->clientFolder($name, now('Europe/Rome')->format('Y')));
        }
        $result = $this->drive(fn () => $drive->upload($data['file'], $folder['id']));
        DB::table('drive_attachments')->insert([
            'id' => (string) Str::uuid(), $key => $id, 'uploaded_by' => $request->user()->id,
            'drive_id' => $drive->root(), 'file_id' => $result['id'], 'folder_id' => $folder['id'],
            'folder_name' => mb_substr($folder['name'], 0, 255), 'name' => mb_substr($data['file']->getClientOriginalName(), 0, 255),
            'size' => $data['file']->getSize(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['message' => 'File caricato su Drive.'], 201);
    }

    private function clientName(Request $request, string $id): ?string
    {
        $scope = $this->scope($request);
        $entity = DB::table($scope)->where('id', $id)->first();
        $seen = [];
        while ($entity) {
            $clientId = $entity->client_id;
            if (! $clientId && $scope === 'tasks' && $entity->project_id) {
                $clientId = DB::table('projects')->where('id', $entity->project_id)->value('client_id');
            }
            if ($clientId) return DB::table('clients')->where('id', $clientId)->value('name');
            if ($scope !== 'tasks' || ! $entity->parent_task_id || isset($seen[$entity->parent_task_id])) break;
            $seen[$entity->parent_task_id] = true;
            $entity = DB::table('tasks')->where('id', $entity->parent_task_id)->first();
        }
        return null;
    }

    public function createFolder(Request $request, string $id, GoogleDriveStorage $drive)
    {
        $this->access($request, $id, true);
        $data = $request->validate(['parent' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255']]);

        return response()->json($this->drive(fn () => $drive->createFolder($data['parent'], $data['name'])), 201);
    }

    public function download(Request $request, string $id, string $fileId, GoogleDriveStorage $drive)
    {
        $key = $this->access($request, $id);
        $file = DB::table('drive_attachments')->where($key, $id)->where('id', $fileId)->first();
        abort_unless($file, 404);
        abort_unless($file->drive_id === $drive->root(), 404);
        $stream = $this->drive(fn () => $drive->download($file->file_id));

        return response()->streamDownload(function () use ($stream) {
            try {
                while (! $stream->eof()) echo $stream->read(65536);
            } finally {
                $stream->close();
            }
        }, str_replace(["\r", "\n", '/', '\\'], '_', $file->name), ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, string $id, string $fileId, GoogleDriveStorage $drive)
    {
        $key = $this->access($request, $id, true);
        $file = DB::table('drive_attachments')->where($key, $id)->where('id', $fileId)->first();
        abort_unless($file && $file->drive_id === $drive->root(), 404);
        if (DB::table('drive_attachments')->where('drive_id', $file->drive_id)->where('file_id', $file->file_id)->where('id', '!=', $fileId)->exists()) {
            throw ValidationException::withMessages(['drive' => 'Il file è collegato anche ad altre schede. Eliminazione bloccata per non rimuovere i loro allegati.']);
        }
        $this->drive(fn () => $drive->trash($file->file_id));
        DB::table('drive_attachments')->where('id', $fileId)->delete();

        return response()->json(['message' => 'File eliminato e spostato nel cestino di Drive.']);
    }
}
