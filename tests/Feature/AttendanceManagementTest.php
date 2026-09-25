<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceRolePermissions;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_request_smart_working_and_see_balance(): void
    {
        $employee = User::factory()->create();
        $this->role($employee, 'editor');

        $this->actingAs($employee)->post(route('profile.absences.store'), [
            'type' => 'smart_working',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('absence_requests', [
            'user_id' => $employee->id,
            'type' => 'smart_working',
            'status' => 'pending',
        ]);

        DB::table('attendance_balances')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $employee->id,
            'year' => 2026, 'type' => 'vacation', 'allocated_minutes' => 2400,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balance = app(AttendanceService::class)->balances($employee->id, 2026);
        $this->assertSame(2400, $balance['vacation']['remaining_minutes']);
    }

    public function test_rejection_requires_reason_and_it_is_saved(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($employee, 'editor');
        $id = $this->absence($employee);

        $this->actingAs($admin)->patch(route('absences.status.update', $id), [
            'status' => 'rejected',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->patch(route('absences.status.update', $id), [
            'status' => 'rejected', 'reason' => 'Copertura del team insufficiente.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('absence_requests', [
            'id' => $id, 'status' => 'rejected', 'decision_reason' => 'Copertura del team insufficiente.',
        ]);
    }

    public function test_manager_cannot_download_or_edit_medical_document(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        Storage::fake('local');
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($manager, 'admin');
        $this->role($employee, 'editor');
        DB::table('profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $employee->id,
            'manager_user_id' => $manager->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $path = 'absence-medical-documents/test.pdf';
        Storage::disk('local')->put($path, 'private');
        $id = $this->absence($employee, 'sickness', $path);

        $this->actingAs($manager)->get(route('absences.medical-document.download', $id))->assertForbidden();
        $this->actingAs($manager)->put(route('absences.update', $id), [
            'type' => 'sickness', 'start_date' => '2026-10-05', 'end_date' => '2026-10-05',
        ])->assertForbidden();
    }

    public function test_manager_report_is_limited_to_own_team(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $manager = User::factory()->create();
        $ownEmployee = User::factory()->create();
        $otherEmployee = User::factory()->create();
        $this->role($manager, 'admin');
        $this->role($ownEmployee, 'editor');
        $this->role($otherEmployee, 'editor');
        DB::table('profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $ownEmployee->id,
            'manager_user_id' => $manager->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('documents.reports.export', [
            'year' => 2026, 'month' => 10, 'format' => 'csv', 'user_id' => $otherEmployee->id,
        ]))->assertForbidden();

        $response = $this->actingAs($manager)->get(route('documents.reports.export', [
            'year' => 2026, 'month' => 10, 'format' => 'csv', 'team_id' => $otherEmployee->id,
        ]))->assertOk();
        $csv = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString($ownEmployee->name, $csv);
        $this->assertStringNotContainsString($otherEmployee->name, $csv);
    }

    public function test_superadmin_can_save_multiple_holidays_without_partial_duplicates(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $admin = User::factory()->create();
        $this->role($admin, 'superadmin');

        $this->actingAs($admin)->post(route('attendance.holidays.store'), [
            'name' => 'Chiusura aziendale',
            'days' => ['2026-12-24', '2026-12-31'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_holidays', 2);

        $this->actingAs($admin)->post(route('attendance.holidays.store'), [
            'name' => 'Altra chiusura',
            'days' => ['2026-12-31', '2027-01-02'],
        ])->assertSessionHasErrors('days');
        $this->assertDatabaseCount('attendance_holidays', 2);
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
    }

    private function absence(User $user, string $type = 'vacation', ?string $medicalPath = null): string
    {
        $id = (string) Str::uuid();
        DB::table('absence_requests')->insert([
            'id' => $id, 'user_id' => $user->id, 'type' => $type,
            'start_date' => '2026-10-05', 'end_date' => '2026-10-05',
            'status' => 'pending', 'medical_document_path' => $medicalPath,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }
}
