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
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_request_smart_working(): void
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

    }

    public function test_other_absence_no_longer_uses_custom_cause_or_auto_approval(): void
    {
        $employee = User::factory()->create();
        $this->role($employee, 'editor');
        DB::table('attendance_causes')->insert([
            'code' => 'old', 'name' => 'Vecchia causale', 'reduces_presence' => true,
            'requires_approval' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($employee)->post(route('profile.absences.store'), [
            'type' => 'other', 'cause_code' => 'old',
            'start_date' => '2026-10-05', 'end_date' => '2026-10-05',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('absence_requests', [
            'user_id' => $employee->id, 'type' => 'other',
            'cause_code' => null, 'status' => 'pending',
        ]);
        $this->assertDatabaseHas('attendance_causes', ['code' => 'old']);
    }

    public function test_editing_legacy_other_absence_preserves_its_cause(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $id = $this->absence($employee, 'other');
        DB::table('absence_requests')->where('id', $id)->update(['cause_code' => 'old']);

        $this->actingAs($admin)->put(route('absences.update', $id), [
            'type' => 'other', 'status' => 'pending', 'start_date' => '2026-10-06',
            'end_date' => '2026-10-06', 'notes' => 'Nota aggiornata',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('absence_requests', [
            'id' => $id, 'cause_code' => 'old', 'notes' => 'Nota aggiornata',
        ]);
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

    public function test_attendance_pdf_report_uses_italian_summary_labels(): void
    {
        $admin = User::factory()->create();
        $this->role($admin, 'superadmin');

        $pdf = $this->actingAs($admin)->get(route('documents.reports.export', [
            'format' => 'pdf', 'year' => 2026, 'month' => 9,
        ]))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $text = (new Parser)->parseContent($pdf)->getText();

        foreach (['Ordinarie', 'Straordinari', 'Ferie', 'Permessi', 'Malattia', 'Ritardi', 'Smart working', 'Banca ore', 'Recuperi', 'Trasferte', 'Altre assenze'] as $label) {
            $this->assertStringContainsString($label, $text);
        }
        $this->assertStringNotContainsString('Vacation', $text);
        $this->assertStringNotContainsString('Sickness', $text);
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

    public function test_calendar_presence_counts_approved_full_day_absences_without_manual_entries(): void
    {
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($employee, 'editor');
        $absenceId = $this->absence($employee);

        $events = app(AttendanceService::class)->calendarEvents($admin->id, true, false, '2026-10-05', '2026-10-05');
        $this->assertSame(2, $events[0]['present']);

        DB::table('absence_requests')->where('id', $absenceId)->update(['status' => 'approved']);
        $events = app(AttendanceService::class)->calendarEvents($admin->id, true, false, '2026-10-05', '2026-10-05');
        $this->assertSame(1, $events[0]['present']);

        DB::table('absence_requests')->where('id', $absenceId)->update(['start_time' => '09:00', 'end_time' => '11:00']);
        $events = app(AttendanceService::class)->calendarEvents($admin->id, true, false, '2026-10-05', '2026-10-05');
        $this->assertSame(2, $events[0]['present']);
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

    public function test_worked_hours_are_calculated_from_approved_absences_and_can_be_corrected(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $this->travelTo(Carbon::parse('2026-09-28 12:00:00', 'Europe/Rome'));
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($employee, 'editor');

        foreach ([
            ['2026-09-21', 'vacation', null, null],
            ['2026-09-22', 'permission', '09:00', '11:00'],
            ['2026-09-23', 'smart_working', null, null],
            ['2026-09-24', 'other', null, null],
        ] as [$day, $type, $start, $end]) {
            DB::table('absence_requests')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $employee->id, 'type' => $type,
                'start_date' => $day, 'end_date' => $day, 'start_time' => $start, 'end_time' => $end,
                'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $url = route('documents.reports.export', [
            'format' => 'csv', 'user_id' => $employee->id,
            'from' => '2026-09-21', 'to' => '2026-09-25',
        ]);
        $workedHours = static function (string $csv): string {
            $rows = array_map(static fn ($line) => str_getcsv($line, ';'), array_values(array_filter(explode("\n", $csv))));
            $headerIndex = array_search('Cognome Nome', array_column($rows, 0), true);
            $columnIndex = array_search('Ore lavorate', $rows[$headerIndex], true);

            return $rows[$headerIndex + 1][$columnIndex];
        };
        $csv = file_get_contents($this->actingAs($admin)->get($url)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertSame('22h', $workedHours($csv));

        DB::table('attendance_entries')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $employee->id, 'day' => '2026-09-25',
            'cause' => 'actual', 'minutes' => 60, 'created_by' => $admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $csv = file_get_contents($this->actingAs($admin)->get($url)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertSame('22h', $workedHours($csv));

        $futureUrl = route('documents.reports.export', [
            'format' => 'csv', 'user_id' => $employee->id,
            'from' => '2026-09-21', 'to' => '2026-09-29',
        ]);
        $futureCsv = file_get_contents($this->actingAs($admin)->get($futureUrl)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertSame('30h', $workedHours($futureCsv));

        $this->actingAs($admin)->post(route('attendance.entries.store'), [
            'user_id' => $employee->id, 'day' => '2026-09-25', 'cause' => 'adjustment', 'minutes' => 300,
        ])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('attendance.entries.store'), [
            'user_id' => $employee->id, 'day' => '2026-09-29', 'cause' => 'adjustment', 'minutes' => 300,
            'note' => 'Rettifica futura non consentita',
        ])->assertSessionHasErrors('day');
        $this->actingAs($admin)->post(route('attendance.entries.store'), [
            'user_id' => $employee->id, 'day' => '2026-09-25', 'cause' => 'adjustment', 'minutes' => 300,
            'note' => 'Uscita anticipata concordata',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'rettifica_ore_lavorate', 'subject_id' => $employee->id]);
        $csv = file_get_contents($this->actingAs($admin)->get($url)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertSame('19h', $workedHours($csv));
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
        $this->actingAs($manager)->get('/attendance/registry/balances/export')->assertNotFound();
    }

    public function test_legacy_balances_and_custom_causes_cannot_be_managed(): void
    {
        $this->withoutMiddleware(EnforceRolePermissions::class);
        $admin = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->actingAs($admin)->get('/attendance/registry/balances?offset=0&limit=10')->assertNotFound();
        $this->actingAs($admin)->post('/attendance/causes', ['code' => 'custom', 'name' => 'Custom'])->assertNotFound();
        $this->actingAs($admin)->put('/attendance/balances/'.$admin->id, ['year' => 2026])->assertNotFound();
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
