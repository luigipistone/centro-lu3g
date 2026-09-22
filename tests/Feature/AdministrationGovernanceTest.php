<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_suspend_archive_and_reactivate_an_account(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'suspended'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'account_status' => 'suspended']);

        $this->actingAs($employee->refresh())->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'archived'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'account_status' => 'archived']);

        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'account_status' => 'active']);
    }

    public function test_superadmin_can_update_the_role_permission_matrix(): void
    {
        $superadmin = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $matrix = [];
        foreach (RolePermissionService::ROLES as $role) {
            foreach (RolePermissionService::definitions() as $definition) {
                $matrix[$role][$definition['key']] = $role === 'superadmin';
            }
        }
        $matrix['editor']['clients.create'] = true;

        $this->actingAs($superadmin)
            ->put(route('settings.roles.update'), ['permissions' => $matrix])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('role_permissions', [
            'role' => 'editor',
            'permission' => 'clients.create',
            'allowed' => true,
        ]);
    }

    public function test_important_mutations_are_recorded_in_the_audit_log(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'suspended'])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superadmin->id,
            'route_name' => 'users.status.update',
            'method' => 'PATCH',
            'status_code' => 302,
        ]);
    }

    public function test_permission_matrix_is_enforced_on_routes(): void
    {
        $employee = User::factory()->create();
        $this->role($employee, 'editor');
        DB::table('role_permissions')
            ->where('role', 'editor')
            ->where('permission', 'clients.view')
            ->update(['allowed' => false]);

        $this->actingAs($employee)->get(route('clients.index'))->assertForbidden();
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
