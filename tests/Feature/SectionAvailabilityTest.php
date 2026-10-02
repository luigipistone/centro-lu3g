<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SectionAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_suspend_and_restore_a_section_without_losing_access(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $manager = $this->userWithRole('admin');
        $employee = $this->userWithRole('editor');
        DB::table('role_permissions')->where('role', 'editor')->whereIn('permission', ['clients.view', 'clients.create'])->update(['allowed' => true]);

        $this->actingAs($superadmin)->patch(route('settings.sections.update', 'clients'), ['enabled' => false])
            ->assertRedirect(route('settings.index', ['tab' => 'sezioni']));
        $this->assertDatabaseHas('section_availability', ['section_key' => 'clients', 'enabled' => false]);
        $this->actingAs($employee)->get(route('clients.index'))->assertStatus(503);
        $this->actingAs($employee)->post(route('clients.store'), [])->assertStatus(503);
        $this->actingAs($manager)->get(route('clients.index'))->assertOk();
        $this->actingAs($manager)->get(route('dashboard'))->assertOk();
        $this->actingAs($superadmin)->get(route('clients.index'))->assertOk();
        $this->actingAs($superadmin)->patch(route('settings.sections.update', 'clients'), ['enabled' => true])->assertRedirect();
        $this->actingAs($employee)->get(route('clients.index'))->assertOk();
    }

    public function test_manager_cannot_change_section_availability(): void
    {
        $manager = $this->userWithRole('admin');
        $this->actingAs($manager)->patch(route('settings.sections.update', 'tasks'), ['enabled' => false])->assertForbidden();
        $this->assertDatabaseCount('section_availability', 0);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
