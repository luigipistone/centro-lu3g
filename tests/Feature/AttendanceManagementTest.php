<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceRolePermissions;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
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

    public function test_holiday_range_is_one_record_and_covers_every_day(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($admin)->post(route('attendance.holidays.store'), [
            'start_day' => '2026-12-24', 'end_day' => '2026-12-28', 'name' => 'Chiusura',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendance_holidays', 1);
        $this->assertDatabaseHas('attendance_holidays', ['day' => '2026-12-24', 'end_day' => '2026-12-28']);
        $this->assertSame(0, app(AttendanceService::class)->workingMinutes($employee->id, Carbon::parse('2026-12-28')));
        $this->assertSame([
            '2026-12-26' => 'Chiusura',
            '2026-12-27' => 'Chiusura',
            '2026-12-28' => 'Chiusura',
        ], app(AttendanceService::class)->calendarHolidays('2026-12-26', '2026-12-30'));
        $this->actingAs($admin)->get(route('calendar.attendance', [
            'from' => '2026-12-26', 'to' => '2026-12-30',
        ]))->assertOk()->assertJsonPath('holidays.2026-12-28', 'Chiusura');

        $this->actingAs($admin)->post(route('attendance.holidays.store'), [
            'start_day' => '2026-12-27', 'end_day' => '2026-12-30', 'name' => 'Doppione',
        ])->assertSessionHasErrors('start_day');
        $this->assertDatabaseCount('attendance_holidays', 1);
    }

    public function test_availability_starts_on_monday_and_covers_only_two_weeks(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00', 'Europe/Rome'));
        $viewer = User::factory()->create();

        $days = app(AttendanceService::class)->availability($viewer->id, true);

        $this->assertCount(14, $days);
        $this->assertSame('2026-09-21', $days[0]['date']);
        $this->assertSame('2026-10-04', $days[13]['date']);
    }

    public function test_attendance_registry_is_paginated_and_exported_without_the_limit(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($employee, 'editor');
        foreach (range(0, 204) as $day) {
            DB::table('attendance_entries')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $employee->id,
                'day' => Carbon::parse('2026-01-01')->addDays($day)->toDateString(), 'cause' => 'actual', 'minutes' => 480,
                'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)->get(route('attendance.registry', ['kind' => 'entries', 'offset' => 0, 'limit' => 10]))
            ->assertOk()->assertJsonCount(10, 'rows')->assertJsonPath('total', 205);
        $this->actingAs($admin)->get(route('attendance.registry', ['kind' => 'entries', 'offset' => 10, 'limit' => 50]))
            ->assertOk()->assertJsonCount(50, 'rows')->assertJsonPath('total', 205);
        $this->actingAs($admin)->get(route('attendance.registry', ['kind' => 'entries', 'offset' => 199, 'limit' => 50]))
            ->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('total', 205);
        $csv = $this->actingAs($admin)->get(route('attendance.registry.export', 'entries'))->assertOk()->streamedContent();
        $this->assertSame(206, substr_count($csv, "\n"));
    }

    public function test_manager_registry_contains_only_their_team(): void
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
        foreach ([$ownEmployee, $otherEmployee] as $employee) {
            DB::table('attendance_entries')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $employee->id,
                'day' => '2026-09-25', 'cause' => 'actual', 'minutes' => 480,
                'created_by' => $manager->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($manager)->get(route('attendance.registry', ['kind' => 'entries', 'offset' => 0, 'limit' => 10]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('rows.0.user_id', $ownEmployee->id);
        $this->actingAs($manager)->get(route('attendance.registry.export', 'balances'))->assertForbidden();
    }

    public function test_balance_registry_loads_in_batches_and_exports_every_year(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        foreach (range(2020, 2025) as $year) {
            foreach (['vacation', 'permission'] as $type) {
                DB::table('attendance_balances')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $employee->id,
                    'year' => $year, 'type' => $type, 'allocated_minutes' => 2400,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $this->actingAs($admin)->get(route('attendance.registry', ['kind' => 'balances', 'offset' => 0, 'limit' => 10]))
            ->assertOk()->assertJsonCount(10, 'rows')->assertJsonPath('total', 12);
        $this->actingAs($admin)->get(route('attendance.registry', ['kind' => 'balances', 'offset' => 10, 'limit' => 50]))
            ->assertOk()->assertJsonCount(2, 'rows');
        $csv = $this->actingAs($admin)->get(route('attendance.registry.export', 'balances'))->assertOk()->streamedContent();
        $this->assertSame(13, substr_count($csv, "\n"));
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
