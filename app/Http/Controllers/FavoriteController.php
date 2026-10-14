<?php

namespace App\Http\Controllers;

use App\Services\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    private function canManage(Request $request): bool
    {
        $role = DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?: 'guest';
        return app(RolePermissionService::class)->allows($role, 'favorites.manage');
    }

    private function isSuperadmin(Request $request): bool
    {
        return DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') === 'superadmin';
    }

    private function visibleFolders(Request $request)
    {
        $userId = $request->user()->id;
        $query = DB::table('favorite_folders as f');
        if (! $this->isSuperadmin($request)) {
            $query->where(function ($q) use ($userId, $request) {
                $q->where('f.created_by', $userId)
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('favorite_folder_user as fu')
                        ->whereColumn('fu.folder_id', 'f.id')->where('fu.user_id', $userId))
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('favorite_folder_team as fg')
                        ->join('favorite_group_user as fgu', 'fgu.group_id', '=', 'fg.group_id')
                        ->whereColumn('fg.folder_id', 'f.id')->where('fgu.user_id', $userId));
                if ($this->canManage($request)) {
                    $q->orWhere('f.visibility', 'shared');
                }
            });
        }
        return $query;
    }

    private function folder(Request $request, string $id): object
    {
        return $this->visibleFolders($request)->where('f.id', $id)->firstOrFail();
    }

    private function canWrite(Request $request, object $folder): bool
    {
        return $this->isSuperadmin($request)
            || $folder->created_by === $request->user()->id
            || ($this->canManage($request) && $folder->visibility === 'shared');
    }

    public function index(Request $request): Response
    {
        $view = $request->route('view', 'items');
        $folders = $this->visibleFolders($request)->select('f.*')->orderBy('f.name')->get();
        $selectedFolder = $view === 'folder-detail' ? $folders->firstWhere('id', $request->route('id')) : null;
        if ($view === 'folder-detail') abort_unless($selectedFolder, 404);
        if (in_array($view, ['group-create', 'group-detail'], true)) abort_unless($this->canManage($request), 403);
        $ids = $folders->pluck('id');
        $items = $ids->isEmpty() ? collect() : DB::table('favorite_items')
            ->whereIn('folder_id', $ids)->orderBy('title')->get();
        $users = DB::table('users')->where('account_status', 'active')->select('id', 'name', 'email')->orderBy('name')->get();
        $groupQuery = DB::table('favorite_groups')->orderBy('name');
        if (! $this->canManage($request)) {
            $groupQuery->whereIn('id', DB::table('favorite_group_user')->where('user_id', $request->user()->id)->select('group_id'));
        }
        $groups = $groupQuery->get();
        $groupIds = $groups->pluck('id');
        $members = $groupIds->isEmpty() ? collect() : DB::table('favorite_group_user')->whereIn('group_id', $groupIds)->get();
        $selectedGroup = $view === 'group-detail' ? $groups->firstWhere('id', $request->route('id')) : null;
        if ($view === 'group-detail') abort_unless($selectedGroup, 404);
        $folderUsers = $ids->isEmpty() ? collect() : DB::table('favorite_folder_user')->whereIn('folder_id', $ids)->get();
        $folderGroups = $ids->isEmpty() ? collect() : DB::table('favorite_folder_team')->whereIn('folder_id', $ids)->get();

        return Inertia::render('Centro/Favorites', [
            'folders' => $folders->map(fn ($folder) => [...(array) $folder,
                'user_ids' => $folderUsers->where('folder_id', $folder->id)->pluck('user_id')->values(),
                'group_ids' => $folderGroups->where('folder_id', $folder->id)->pluck('group_id')->values(),
                'can_edit' => $this->canWrite($request, $folder),
            ]),
            'items' => $items,
            'users' => $this->canManage($request) ? $users : [],
            'groups' => $groups->map(fn ($group) => [...(array) $group,
                'user_ids' => $this->canManage($request) ? $members->where('group_id', $group->id)->pluck('user_id')->values() : [],
                'members_count' => $members->where('group_id', $group->id)->count(),
            ]),
            'canManage' => $this->canManage($request),
            'view' => $view,
            'selectedFolderId' => $selectedFolder?->id,
            'selectedGroupId' => $selectedGroup?->id,
        ]);
    }

    private function folderData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'visibility' => ['required', 'in:personal,shared'],
            'color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'user_ids' => ['array'], 'user_ids.*' => ['uuid', 'exists:users,id'],
            'group_ids' => ['array'], 'group_ids.*' => ['uuid', 'exists:favorite_groups,id'],
        ]);
        if ($data['visibility'] === 'shared') {
            abort_unless($this->canManage($request), 403);
            if (empty($data['user_ids']) && empty($data['group_ids'])) {
                throw ValidationException::withMessages(['user_ids' => 'Seleziona almeno un utente o un gruppo.']);
            }
        }
        return $data;
    }

    private function syncRecipients(string $id, array $data): void
    {
        DB::table('favorite_folder_user')->where('folder_id', $id)->delete();
        DB::table('favorite_folder_team')->where('folder_id', $id)->delete();
        if ($data['visibility'] !== 'shared') return;
        foreach (array_unique($data['user_ids'] ?? []) as $userId) {
            DB::table('favorite_folder_user')->insert(['folder_id' => $id, 'user_id' => $userId]);
        }
        foreach (array_unique($data['group_ids'] ?? []) as $groupId) {
            DB::table('favorite_folder_team')->insert(['folder_id' => $id, 'group_id' => $groupId]);
        }
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $data = $this->folderData($request);
        DB::transaction(function () use ($request, $data) {
            $id = (string) Str::uuid();
            DB::table('favorite_folders')->insert([
                'id' => $id, 'name' => $data['name'], 'visibility' => $data['visibility'],
                'created_by' => $request->user()->id, 'color' => $data['color'] ?? '#0B6EF3', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncRecipients($id, $data);
        });
        return back()->with('success', 'Cartella creata.');
    }

    public function updateFolder(Request $request, string $id): RedirectResponse
    {
        $folder = $this->folder($request, $id);
        abort_unless($this->canWrite($request, $folder), 403);
        $data = $this->folderData($request);
        DB::transaction(function () use ($id, $data, $folder) {
            DB::table('favorite_folders')->where('id', $id)->update([
                'name' => $data['name'], 'visibility' => $data['visibility'], 'color' => $data['color'] ?? $folder->color, 'updated_at' => now(),
            ]);
            $this->syncRecipients($id, $data);
        });
        return back()->with('success', 'Cartella aggiornata.');
    }

    public function destroyFolder(Request $request, string $id): RedirectResponse
    {
        $folder = $this->folder($request, $id);
        abort_unless($this->canWrite($request, $folder), 403);
        DB::table('favorite_folders')->where('id', $id)->delete();
        return back()->with('success', 'Cartella eliminata.');
    }

    private function groupData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'user_ids' => ['array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);
    }

    private function syncGroupMembers(string $id, array $userIds): void
    {
        DB::table('favorite_group_user')->where('group_id', $id)->delete();
        foreach (array_unique($userIds) as $userId) {
            DB::table('favorite_group_user')->insert(['group_id' => $id, 'user_id' => $userId]);
        }
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $data = $this->groupData($request);
        DB::transaction(function () use ($request, $data) {
            $id = (string) Str::uuid();
            DB::table('favorite_groups')->insert([
                'id' => $id, 'name' => $data['name'], 'description' => $data['description'] ?? null,
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncGroupMembers($id, $data['user_ids'] ?? []);
        });
        return back()->with('success', 'Gruppo creato.');
    }

    public function updateGroup(Request $request, string $id): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        abort_unless(DB::table('favorite_groups')->where('id', $id)->exists(), 404);
        $data = $this->groupData($request);
        DB::transaction(function () use ($id, $data) {
            DB::table('favorite_groups')->where('id', $id)->update([
                'name' => $data['name'], 'description' => $data['description'] ?? null, 'updated_at' => now(),
            ]);
            $this->syncGroupMembers($id, $data['user_ids'] ?? []);
        });
        return back()->with('success', 'Gruppo aggiornato.');
    }

    public function destroyGroup(Request $request, string $id): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        abort_unless(DB::table('favorite_groups')->where('id', $id)->exists(), 404);
        DB::table('favorite_groups')->where('id', $id)->delete();
        return back()->with('success', 'Gruppo eliminato.');
    }

    private function itemData(Request $request): array
    {
        $data = $request->validate([
            'folder_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:180'],
            'url' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $url = filter_var($data['url'], FILTER_VALIDATE_URL);
        if (! $url || ! in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true)) {
            throw ValidationException::withMessages(['url' => 'Inserisci un indirizzo http o https valido.']);
        }
        return $data;
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $this->itemData($request);
        abort_unless($this->canWrite($request, $this->folder($request, $data['folder_id'])), 403);
        DB::table('favorite_items')->insert([
            'id' => (string) Str::uuid(), 'folder_id' => $data['folder_id'],
            'created_by' => $request->user()->id, 'title' => $data['title'],
            'url' => $data['url'], 'description' => $data['description'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Preferito aggiunto.');
    }

    private function item(Request $request, string $id): object
    {
        $item = DB::table('favorite_items')->where('id', $id)->firstOrFail();
        $this->folder($request, $item->folder_id);
        return $item;
    }

    public function updateItem(Request $request, string $id): RedirectResponse
    {
        $item = $this->item($request, $id);
        abort_unless($this->canWrite($request, $this->folder($request, $item->folder_id)), 403);
        $data = $this->itemData($request);
        abort_unless($this->canWrite($request, $this->folder($request, $data['folder_id'])), 403);
        DB::table('favorite_items')->where('id', $id)->update([
            'folder_id' => $data['folder_id'], 'title' => $data['title'],
            'url' => $data['url'], 'description' => $data['description'] ?? null, 'updated_at' => now(),
        ]);
        return back()->with('success', 'Preferito aggiornato.');
    }

    public function destroyItem(Request $request, string $id): RedirectResponse
    {
        $item = $this->item($request, $id);
        abort_unless($this->canWrite($request, $this->folder($request, $item->folder_id)), 403);
        DB::table('favorite_items')->where('id', $id)->delete();
        return back()->with('success', 'Preferito eliminato.');
    }
}
