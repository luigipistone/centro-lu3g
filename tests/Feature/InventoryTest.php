<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
}
