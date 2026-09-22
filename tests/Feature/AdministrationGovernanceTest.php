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

    public function test_superadmin_can_suspend_and_approve_account_archival(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'suspended'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'account_status' => 'suspended']);

        $this->actingAs($employee->refresh())->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAs($superadmin)->post(route('users.archive-requests.store', $employee), ['reason' => 'Rapporto di lavoro terminato.'])->assertRedirect();
        $requestId = DB::table('account_archive_requests')->where('user_id', $employee->id)->value('id');
        $this->actingAs($superadmin)->patch(route('users.archive-requests.review', $requestId), ['decision' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'account_status' => 'archived']);
        $this->actingAs($employee->refresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_employee_requests_archival_without_deleting_the_account(): void
    {
        $employee = User::factory()->create(['password' => bcrypt('password')]);
        $superadmin = User::factory()->create();
        $this->role($employee, 'editor');
        $this->role($superadmin, 'superadmin');

        $this->actingAs($employee)->post(route('profile.archive-request'), [
            'password' => 'password',
            'reason' => 'Desidero chiudere il mio account aziendale.',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $employee->id]);
        $this->assertDatabaseHas('account_archive_requests', ['user_id' => $employee->id, 'status' => 'pending']);
        $this->assertDatabaseHas('notifications', ['user_id' => $superadmin->id, 'type' => 'account_archive_requested']);
    }

    public function test_physical_deletion_requires_an_archived_account_and_keeps_a_permanent_reason_log(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create(['account_status' => 'archived', 'archived_at' => now()]);
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($superadmin)->delete(route('users.physical-destroy', $employee), [
            'reason' => 'Richiesta privacy verificata e periodo di conservazione concluso.',
            'confirmation' => 'ELIMINA DEFINITIVAMENTE',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $employee->id,
            'action' => 'eliminazione_fisica_utente',
        ]);
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

    public function test_manager_can_explicitly_save_operational_fields_but_not_contract_fields(): void
    {
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($manager, 'admin');
        $this->role($employee, 'editor');
        $this->assertTrue(app(RolePermissionService::class)->allows('admin', 'users.profile.operational.update'));

        $this->actingAs($manager)->put(route('users.sensitive.update', $employee), [
            'section' => 'operational',
            'confirmed' => true,
            'job_title' => 'Designer',
            'department' => 'Creativo',
            'weekly_hours' => 32,
            'part_time' => true,
            'part_time_percentage' => 80,
            'smartworking_days' => ['monday'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profiles', [
            'user_id' => $employee->id,
            'job_title' => 'Designer',
            'department' => 'Creativo',
            'weekly_hours' => 32,
        ]);

        $this->actingAs($manager)->put(route('users.sensitive.update', $employee), [
            'section' => 'contract',
            'confirmed' => true,
            'employment_status' => 'active',
            'employee_code' => 'RISERVATO',
        ])->assertForbidden();
    }

    public function test_superadmin_sensitive_save_is_logged_with_changed_fields(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($superadmin)->put(route('users.sensitive.update', $employee), [
            'section' => 'contract',
            'confirmed' => true,
            'employee_code' => 'LU3G-001',
            'employment_status' => 'active',
            'hire_date' => '2026-01-12',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = DB::table('audit_logs')
            ->where('action', 'modifica_profilo_riservato')
            ->where('subject_id', $employee->id)
            ->first();

        $this->assertNotNull($log);
        $metadata = json_decode($log->metadata, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('contract', $metadata['section']);
        $this->assertArrayHasKey('employee_code', $metadata['changed_fields']);
    }

    public function test_sensitive_save_requires_explicit_confirmation(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($superadmin)->put(route('users.sensitive.update', $employee), [
            'section' => 'contract',
            'employment_status' => 'active',
            'employee_code' => 'LU3G-002',
        ])->assertSessionHasErrors('confirmed');

        $this->assertDatabaseMissing('profiles', ['user_id' => $employee->id, 'employee_code' => 'LU3G-002']);
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
