<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeDossierTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_and_replace_a_dossier_item(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($employee, 'editor');

        $this->actingAs($admin)->post(route('users.dossier-items.store', $employee->id), [
            'type' => 'identity_document',
            'title' => 'Carta di identità',
            'identifier' => 'AA1234567',
            'issued_at' => '2026-01-10',
            'expires_at' => '2036-01-10',
            'file' => UploadedFile::fake()->create('identita.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $first = DB::table('employee_dossier_items')->first();
        $this->assertSame(1, $first->version);

        $this->actingAs($admin)->post(route('users.dossier-items.store', $employee->id), [
            'type' => 'identity_document',
            'title' => 'Carta di identità aggiornata',
            'identifier' => 'BB7654321',
            'issued_at' => '2026-09-22',
            'expires_at' => '2036-09-22',
            'replaces_id' => $first->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull(DB::table('employee_dossier_items')->where('id', $first->id)->value('replaced_at'));
        $this->assertDatabaseHas('employee_dossier_items', ['user_id' => $employee->id, 'version' => 2, 'replaces_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $employee->id, 'action' => 'nuova_versione_fascicolo']);
    }

    public function test_employee_can_download_own_private_dossier_file(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create();
        $this->role($employee, 'editor');
        $path = 'employee-dossier/'.$employee->id.'/attestato.pdf';
        Storage::disk('local')->put($path, 'pdf');
        $id = (string) Str::uuid();
        DB::table('employee_dossier_items')->insert([
            'id' => $id, 'user_id' => $employee->id, 'type' => 'safety_course', 'title' => 'Sicurezza',
            'classification' => 'standard', 'file_path' => $path, 'file_name' => 'attestato.pdf',
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($employee)->get(route('users.dossier-items.file', [$employee->id, $id]))->assertOk();
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
    }
}
