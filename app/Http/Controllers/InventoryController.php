<?php

namespace App\Http\Controllers;

use App\Services\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    private function authorizePermission(Request $request, string $permission): void
    {
        $role = DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?: 'guest';
        abort_unless(app(RolePermissionService::class)->allows($role, $permission), 403);
        $request->attributes->set('audit_permissions_used', array_values(array_unique([
            ...$request->attributes->get('audit_permissions_used', []), $permission,
        ])));
    }

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'inventory.view');
        $role = DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?: 'guest';
        $canManage = app(RolePermissionService::class)->allows($role, 'inventory.manage');
        $units = DB::table('inventory_units')
            ->leftJoin('users', 'users.id', '=', 'inventory_units.assigned_user_id')
            ->orderBy('inventory_units.created_at')
            ->get(['inventory_units.*', 'users.name as assigned_user_name'])
            ->groupBy('item_id');
        $items = DB::table('inventory_items')->orderBy('name')->get()
            ->map(fn ($item) => [
                ...(array) $item,
                'units' => $units->get($item->id, collect())->values(),
                'quantity' => $item->type === 'physical' ? $units->get($item->id, collect())->count() : null,
                'model_url' => $item->model_path ? route('inventory.model', $item->id).'?v='.strtotime($item->updated_at) : null,
                'logo_url' => $item->type === 'digital' && $item->access_url ? route('inventory.logo', $item->id).'?v='.strtotime($item->updated_at) : null,
            ]);

        return Inertia::render('Centro/Inventory', [
            'items' => $items,
            'users' => $canManage ? DB::table('users')->where('account_status', 'active')->orderBy('name')->get(['id', 'name']) : [],
            'canManage' => $canManage,
        ]);
    }

    private function itemPayload(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['physical', 'digital'])],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'model_year' => ['nullable', 'integer', 'between:1900,2100'],
            'license_type' => ['nullable', 'string', 'max:120'],
            'capacity_total' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'used_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'renewal_date' => ['nullable', 'date'],
            'access_url' => ['nullable', 'url', 'starts_with:https://,http://', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'model_file' => ['nullable', 'file', 'max:20480'],
        ]);
    }

    private function storeModel(Request $request): ?string
    {
        $file = $request->file('model_file');
        if (! $file) return null;
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fread($handle, 12);
        fclose($handle);
        if (strtolower($file->getClientOriginalExtension()) !== 'glb'
            || strlen($header) !== 12 || substr($header, 0, 4) !== 'glTF'
            || unpack('V', substr($header, 4, 4))[1] !== 2
            || unpack('V', substr($header, 8, 4))[1] !== $file->getSize()) {
            throw ValidationException::withMessages(['model_file' => 'Carica un modello GLB valido (massimo 20 MB).']);
        }
        $contents = file_get_contents($file->getRealPath());
        $jsonLength = strlen($contents) >= 20 ? unpack('V', substr($contents, 12, 4))[1] : 0;
        $document = $jsonLength > 0 && $jsonLength <= strlen($contents) - 20
            && substr($contents, 16, 4) === 'JSON'
            ? json_decode(substr($contents, 20, $jsonLength), true) : null;
        if (($document['asset']['version'] ?? null) !== '2.0') {
            throw ValidationException::withMessages(['model_file' => 'Il GLB non contiene un modello 3D valido.']);
        }
        foreach (array_merge($document['buffers'] ?? [], $document['images'] ?? []) as $resource) {
            if (isset($resource['uri']) && ! str_starts_with($resource['uri'], 'data:')) {
                throw ValidationException::withMessages(['model_file' => 'Il modello deve includere le risorse al suo interno.']);
            }
        }

        return $file->store('inventory-models', 'local');
    }

    public function model(Request $request, string $id)
    {
        $this->authorizePermission($request, 'inventory.view');
        $path = DB::table('inventory_items')->where('id', $id)->where('type', 'physical')->value('model_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'model/gltf-binary', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function logo(Request $request, string $id)
    {
        $this->authorizePermission($request, 'inventory.view');
        $url = DB::table('inventory_items')->where('id', $id)->where('type', 'digital')->value('access_url');
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;
        abort_unless($host && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME), 404);

        $icon = Cache::remember('inventory-logo:v2:'.hash('sha256', $host), now()->addDay(), function () use ($host) {
            try {
                $response = Http::timeout(4)->get('https://www.google.com/s2/favicons', ['domain' => $host, 'sz' => 128]);
                if ($response->successful() && strlen($response->body()) <= 256 * 1024) {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($response->body());
                    if (in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                        return ['body' => $response->body(), 'mime' => $mime];
                    }
                }
            } catch (\Throwable) {
                return null;
            }
            return null;
        });
        abort_unless($icon, 404);
        return response($icon['body'], 200, ['Content-Type' => $icon['mime'], 'Cache-Control' => 'private, max-age=86400']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'inventory.manage');
        $data = $this->itemPayload($request);
        $this->validateCapacity($data);
        if ($request->hasFile('model_file') && $data['type'] !== 'physical') {
            throw ValidationException::withMessages(['model_file' => 'Il modello 3D è disponibile solo per gli oggetti fisici.']);
        }
        $modelPath = $this->storeModel($request);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($data, $id, $request, $modelPath) {
            DB::table('inventory_items')->insert([
                ...collect($data)->except(['quantity', 'model_file'])->all(),
                'model_path' => $modelPath,
                'id' => $id, 'created_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($data['type'] === 'physical') {
                for ($i = 0; $i < ($data['quantity'] ?? 1); $i++) {
                    DB::table('inventory_units')->insert([
                        'id' => (string) Str::uuid(), 'item_id' => $id,
                        'condition' => 'available', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('status', 'Elemento aggiunto all’inventario.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'inventory.manage');
        $item = DB::table('inventory_items')->where('id', $id)->first();
        abort_unless($item, 404);
        $data = $this->itemPayload($request);
        $this->validateCapacity($data);
        abort_unless($data['type'] === $item->type, 422);
        $modelPath = $this->storeModel($request);
        DB::table('inventory_items')->where('id', $id)->update([
            ...collect($data)->except(['type', 'quantity', 'model_file'])->all(),
            ...($modelPath ? ['model_path' => $modelPath] : []), 'updated_at' => now(),
        ]);
        if ($modelPath && $item->model_path) Storage::disk('local')->delete($item->model_path);

        return back()->with('status', 'Elemento aggiornato.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'inventory.manage');
        abort_unless(DB::table('inventory_items')->where('id', $id)->exists(), 404);
        abort_if(DB::table('inventory_units')->where('item_id', $id)->whereNotNull('assigned_user_id')->exists(), 422, 'Restituisci gli oggetti assegnati prima di eliminare questa voce.');
        $modelPath = DB::table('inventory_items')->where('id', $id)->value('model_path');
        DB::table('inventory_items')->where('id', $id)->delete();
        if ($modelPath) Storage::disk('local')->delete($modelPath);

        return back()->with('status', 'Elemento eliminato.');
    }

    private function validateCapacity(array $data): void
    {
        if ($data['type'] === 'digital' && isset($data['capacity_total'], $data['used_count'])) {
            if ($data['used_count'] > $data['capacity_total']) {
                throw ValidationException::withMessages(['used_count' => 'Le licenze utilizzate non possono superare la capienza.']);
            }
        }
    }

    public function storeUnit(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'inventory.manage');
        abort_unless(DB::table('inventory_items')->where('id', $id)->where('type', 'physical')->exists(), 404);
        DB::table('inventory_units')->insert([
            'id' => (string) Str::uuid(), 'item_id' => $id, 'condition' => 'available',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Unità aggiunta.');
    }

    public function updateUnit(Request $request, string $id, string $unitId): RedirectResponse
    {
        $this->authorizePermission($request, 'inventory.manage');
        abort_unless(DB::table('inventory_units')->where('id', $unitId)->where('item_id', $id)->exists(), 404);
        $data = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'office' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', Rule::in(['available', 'assigned', 'repair', 'retired'])],
            'purchased_at' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($data['assigned_user_id'] ?? null) {
            $data['condition'] = 'assigned';
        } elseif ($data['condition'] === 'assigned') {
            $data['condition'] = 'available';
        }
        DB::table('inventory_units')->where('id', $unitId)->update([...$data, 'updated_at' => now()]);

        return back()->with('status', 'Unità aggiornata.');
    }

    public function destroyUnit(Request $request, string $id, string $unitId): RedirectResponse
    {
        $this->authorizePermission($request, 'inventory.manage');
        $unit = DB::table('inventory_units')->where('id', $unitId)->where('item_id', $id)->first();
        abort_unless($unit, 404);
        abort_if($unit->assigned_user_id, 422, 'Restituisci l’oggetto prima di eliminarlo.');
        DB::table('inventory_units')->where('id', $unitId)->delete();

        return back()->with('status', 'Unità eliminata.');
    }
}
