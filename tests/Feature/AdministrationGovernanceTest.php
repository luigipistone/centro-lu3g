<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Database\QueryException;
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
        $log = DB::table('audit_logs')->where('route_name', 'settings.roles.update')->first();
        $this->assertContains('settings.manage', json_decode($log->permissions_used, true));
        $this->assertFalse(json_decode($log->state_before, true)['editor.clients.create']);
        $this->assertTrue(json_decode($log->state_after, true)['editor.clients.create']);
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
        $log = DB::table('audit_logs')->where('route_name', 'users.status.update')->latest('created_at')->first();
        $this->assertContains('users.profile.security.update', json_decode($log->permissions_used, true));
        $this->assertSame('active', json_decode($log->state_before, true)['account_status']);
        $this->assertSame('suspended', json_decode($log->state_after, true)['account_status']);

        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'active'])->assertRedirect();
        $this->assertSame(2, DB::table('audit_logs')->where('route_name', 'users.status.update')->count());
    }

    public function test_archive_request_and_review_share_an_audit_reference_and_reason(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($superadmin)->post(route('users.archive-requests.store', $employee), [
            'reason' => 'Rapporto terminato e accesso non più necessario.',
        ])->assertRedirect();
        $requestId = DB::table('account_archive_requests')->where('user_id', $employee->id)->value('id');
        $this->actingAs($superadmin)->patch(route('users.archive-requests.review', $requestId), [
            'decision' => 'approved', 'note' => 'Proprietà operative verificate.',
        ])->assertRedirect();

        $created = DB::table('audit_logs')->where('route_name', 'users.archive-requests.store')->first();
        $reviewed = DB::table('audit_logs')->where('route_name', 'users.archive-requests.review')->first();
        $this->assertSame($requestId, $created->related_request_id);
        $this->assertSame('Rapporto terminato e accesso non più necessario.', $created->reason);
        $this->assertSame($requestId, $reviewed->related_request_id);
        $this->assertSame($superadmin->id, $reviewed->reviewed_by);
        $this->assertSame('Proprietà operative verificate.', $reviewed->reason);
        $this->assertSame('pending', json_decode($reviewed->state_before, true)['status']);
        $this->assertSame('approved', json_decode($reviewed->state_after, true)['status']);
    }

    public function test_denied_and_invalid_actions_are_logged_without_sensitive_input(): void
    {
        $employee = User::factory()->create();
        $this->role($employee, 'editor');

        $this->actingAs($employee)->put(route('settings.roles.update'), [
            'password' => 'never-log-this',
        ])->assertForbidden();
        $denied = DB::table('audit_logs')->where('route_name', 'settings.roles.update')->first();
        $this->assertSame(403, $denied->status_code);
        $this->assertSame('HttpError', $denied->failure_type);
        $this->assertContains('settings.manage', json_decode($denied->permissions_used, true));
        $this->assertStringNotContainsString('never-log-this', json_encode($denied));

        $this->actingAs($employee)->post(route('tasks.store'), ['title' => ''])->assertSessionHasErrors();
        $invalid = DB::table('audit_logs')->where('route_name', 'tasks.store')->first();
        $this->assertSame(422, $invalid->status_code);
        $this->assertSame('ValidationException', $invalid->failure_type);
    }

    public function test_suspended_account_attempt_is_logged_as_denied(): void
    {
        $employee = User::factory()->create(['account_status' => 'suspended']);
        $this->role($employee, 'editor');

        $this->actingAs($employee)->post(route('tasks.store'), ['title' => 'Non consentita'])->assertRedirect(route('login'));

        $log = DB::table('audit_logs')->where('route_name', 'tasks.store')->first();
        $this->assertSame($employee->id, $log->user_id);
        $this->assertSame(403, $log->status_code);
        $this->assertSame('AccountInactive', $log->failure_type);
        $this->assertDatabaseMissing('tasks', ['title' => 'Non consentita']);
    }

    public function test_audit_log_is_append_only_and_export_is_superadmin_only(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');
        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'suspended'])->assertRedirect();
        $id = DB::table('audit_logs')->where('route_name', 'users.status.update')->value('id');

        try {
            DB::table('audit_logs')->where('id', $id)->update(['action' => 'altered']);
            $this->fail('Audit log update should be rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        try {
            DB::table('audit_logs')->where('id', $id)->delete();
            $this->fail('Audit log delete should be rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $dates = ['from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()];
        $this->actingAs($employee)->get(route('settings.logs.download', $dates))->assertForbidden();
        $this->actingAs($superadmin)->get(route('settings.logs.download', $dates))->assertOk();
    }

    public function test_audit_filters_apply_to_screen_and_download(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');
        $this->actingAs($employee)->put(route('settings.roles.update'), [])->assertForbidden();
        $this->actingAs($superadmin)->patch(route('users.status.update', $employee), ['status' => 'suspended'])->assertRedirect();

        $this->actingAs($superadmin)->get(route('settings.index', [
            'tab' => 'log', 'log_from' => now()->subDay()->toDateString(), 'log_to' => now()->addDay()->toDateString(),
            'log_area' => 'settings', 'log_user' => $employee->id, 'log_result' => 'error',
        ]))->assertInertia(fn ($page) => $page->has('auditLogs', 1)->where('auditLogs.0.status_code', 403));

        $csv = $this->actingAs($superadmin)->get(route('settings.logs.download', [
            'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString(),
            'area' => 'settings', 'user' => $employee->id, 'result' => 'error',
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('settings.roles.update', $csv);
        $this->assertStringNotContainsString('users.status.update', $csv);
    }

    public function test_task_audit_keeps_operational_changes_without_free_text_or_secrets(): void
    {
        $employee = User::factory()->create();
        $this->role($employee, 'editor');

        $this->actingAs($employee)->post(route('tasks.store'), [
            'title' => 'Riservato', 'description' => 'Non registrare questo contenuto',
            'task_type' => 'task', 'status' => 'todo', 'priority' => 'medium',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $id = DB::table('tasks')->value('id');
        $created = DB::table('audit_logs')->where('route_name', 'tasks.store')->first();
        $this->assertSame($id, $created->subject_id);
        $this->assertContains('tasks.create', json_decode($created->permissions_used, true));
        $this->assertSame('medium', json_decode($created->state_after, true)['priority']);
        $this->assertStringNotContainsString('Riservato', $created->state_after);

        $this->actingAs($employee)->patch(route('tasks.status.update', $id), ['status' => 'in_progress'])->assertRedirect();
        $updated = DB::table('audit_logs')->where('route_name', 'tasks.status.update')->first();
        $this->assertSame(['status' => 'todo'], json_decode($updated->state_before, true));
        $this->assertSame(['status' => 'in_progress'], json_decode($updated->state_after, true));
        $this->assertContains('tasks.update', json_decode($updated->permissions_used, true));
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
        $this->assertContains('users.profile.contract.update', json_decode($log->permissions_used, true));
        $this->assertSame('2026-01-12', json_decode($log->state_after, true)['hire_date']);
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
