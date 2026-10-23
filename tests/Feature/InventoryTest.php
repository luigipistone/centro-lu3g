<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role,
        ]);

        return $user;
    }

    public function test_initial_inventory_contains_the_provided_assets_and_licenses(): void
    {
        $this->assertSame(7, DB::table('inventory_items')->where('type', 'physical')->count());
        $this->assertSame(22, DB::table('inventory_units')->count());
        $this->assertSame(18, DB::table('inventory_items')->where('type', 'digital')->count());
        $this->assertDatabaseHas('inventory_items', ['name' => 'iMac 23" M3 2023']);
        $this->assertDatabaseHas('inventory_items', ['name' => 'WP Rocket', 'capacity_total' => 50]);
    }

    public function test_manager_can_view_inventory_but_employee_cannot_by_default(): void
    {
        $manager = $this->withRole('admin');
        $employee = $this->withRole('editor');

        $this->actingAs($manager)->get(route('inventory.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Centro/Inventory')->where('canManage', true));
        $this->actingAs($employee)->get(route('inventory.index'))->assertForbidden();
    }

    public function test_manager_can_create_and_assign_a_physical_unit(): void
    {
        $manager = $this->withRole('admin');
        $employee = $this->withRole('editor');

        $this->actingAs($manager)->post(route('inventory.store'), [
            'type' => 'physical', 'name' => 'Tastiera', 'category' => 'Periferica', 'quantity' => 2,
        ])->assertSessionHasNoErrors();
        $item = DB::table('inventory_items')->where('name', 'Tastiera')->firstOrFail();
        $this->assertSame(2, DB::table('inventory_units')->where('item_id', $item->id)->count());
        $unit = DB::table('inventory_units')->where('item_id', $item->id)->firstOrFail();

        $this->actingAs($manager)->put(route('inventory.units.update', [$item->id, $unit->id]), [
            'serial_number' => 'KB-01', 'assigned_user_id' => $employee->id,
            'condition' => 'available', 'office' => 'Milano',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventory_units', [
            'id' => $unit->id, 'serial_number' => 'KB-01',
            'assigned_user_id' => $employee->id, 'condition' => 'assigned',
        ]);
        $this->actingAs($manager)->delete(route('inventory.units.destroy', [$item->id, $unit->id]))->assertStatus(422);
    }

    public function test_physical_model_upload_is_private_and_available_to_inventory_viewers(): void
    {
        Storage::fake('local');
        $manager = $this->withRole('admin');
        $employee = $this->withRole('editor');
        $file = new UploadedFile(public_path('models/inventory/monitor.glb'), 'device.glb', 'model/gltf-binary', null, true);

        $this->actingAs($manager)->post(route('inventory.store'), [
            'type' => 'physical', 'name' => 'Nuovo monitor', 'quantity' => 1, 'model_file' => $file,
        ])->assertSessionHasNoErrors();
        $item = DB::table('inventory_items')->where('name', 'Nuovo monitor')->firstOrFail();
        Storage::disk('local')->assertExists($item->model_path);
        $this->actingAs($manager)->get(route('inventory.model', $item->id))->assertOk()
            ->assertHeader('content-type', 'model/gltf-binary');
        $this->actingAs($employee)->get(route('inventory.model', $item->id))->assertForbidden();
    }

    public function test_invalid_model_is_rejected(): void
    {
        $manager = $this->withRole('admin');
        $this->actingAs($manager)->post(route('inventory.store'), [
            'type' => 'physical', 'name' => 'Modello non valido', 'quantity' => 1,
            'model_file' => UploadedFile::fake()->createWithContent('invalid.glb', 'not a glb'),
        ])->assertSessionHasErrors('model_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Modello non valido']);
    }

    public function test_self_contained_gltf_can_be_uploaded_and_served(): void
    {
        Storage::fake('local');
        $manager = $this->withRole('admin');
        $gltf = json_encode([
            'asset' => ['version' => '2.0'],
            'buffers' => [['uri' => 'data:application/octet-stream;base64,AA==', 'byteLength' => 1]],
            'scenes' => [['nodes' => []]],
            'scene' => 0,
        ]);

        $this->actingAs($manager)->post(route('inventory.store'), [
            'type' => 'physical', 'name' => 'Modello glTF', 'quantity' => 1,
            'model_file' => UploadedFile::fake()->createWithContent('device.gltf', $gltf),
        ])->assertSessionHasNoErrors();
        $item = DB::table('inventory_items')->where('name', 'Modello glTF')->firstOrFail();
        $this->assertStringEndsWith('.gltf', $item->model_path);
        Storage::disk('local')->assertExists($item->model_path);
        $this->actingAs($manager)->get(route('inventory.model', $item->id))->assertOk()
            ->assertHeader('content-type', 'model/gltf+json');
    }

    public function test_gltf_with_external_resources_is_rejected(): void
    {
        $manager = $this->withRole('admin');
        $gltf = json_encode([
            'asset' => ['version' => '2.0'],
            'buffers' => [['uri' => 'mesh.bin', 'byteLength' => 1]],
        ]);

        $this->actingAs($manager)->post(route('inventory.store'), [
            'type' => 'physical', 'name' => 'Modello incompleto', 'quantity' => 1,
            'model_file' => UploadedFile::fake()->createWithContent('device.gltf', $gltf),
        ])->assertSessionHasErrors('model_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Modello incompleto']);
    }

    public function test_digital_logo_uses_its_access_domain_and_inventory_permissions(): void
    {
        $manager = $this->withRole('admin');
        $employee = $this->withRole('editor');
        $item = DB::table('inventory_items')->where('name', 'WPML')->firstOrFail();
        Cache::forget('inventory-logo:v3:'.hash('sha256', 'wpml.org'));
        $png = file_get_contents(public_path('apple-touch-icon.png'));
        Http::fake(['www.google.com/s2/favicons*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs($manager)->get(route('inventory.logo', $item->id))->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertContent($png);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'domain=wpml.org'));
        $this->actingAs($employee)->get(route('inventory.logo', $item->id))->assertForbidden();
    }

    public function test_digital_logo_accepts_jpeg_favicons(): void
    {
        $manager = $this->withRole('admin');
        $item = DB::table('inventory_items')->where('name', 'Animation Addon Pro')->firstOrFail();
        Cache::forget('inventory-logo:v3:'.hash('sha256', 'animation-addons.com'));
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        Http::fake(['www.google.com/s2/favicons*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->actingAs($manager)->get(route('inventory.logo', $item->id))->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertContent($jpeg);
    }
}
