<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class ClientCsvImportTest extends TestCase
{
    use ActsAsWorkflowAdmin;
    use RefreshDatabase;

    public function test_previews_then_imports_valid_clients_and_skips_duplicates(): void
    {
        $admin = User::factory()->create();
        $csv = "Nome;Ragione sociale;Email;Partita IVA;Pubblica amministrazione\nAlfa;Alfa Srl;alfa@example.test;12345678901;si\nBeta;;beta@example.test;98765432100;no\n;Senza nome;;;\n";
        $file = fn () => UploadedFile::fake()->createWithContent('clienti.csv', $csv);

        $this->actingAsWorkflowAdmin($admin)->postJson(route('clients.import.preview'), ['file' => $file()])
            ->assertOk()->assertJsonPath('count', 2);
        $this->assertDatabaseCount('clients', 0);

        $this->actingAsWorkflowAdmin($admin)->post(route('clients.import.store'), ['file' => $file()])
            ->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['name' => 'Alfa', 'legal_name' => 'Alfa Srl', 'is_pa' => true]);
        $this->assertDatabaseCount('clients', 2);

        $this->actingAsWorkflowAdmin($admin)->post(route('clients.import.store'), ['file' => $file()])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_import_requires_name_header_and_create_permission(): void
    {
        $admin = User::factory()->create();
        $invalid = UploadedFile::fake()->createWithContent('clienti.csv', "Email,Telefono\na@example.test,123\n");
        $this->actingAsWorkflowAdmin($admin)->postJson(route('clients.import.preview'), ['file' => $invalid])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $employee = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $employee->id, 'role' => 'editor']);
        $this->actingAs($employee)->get(route('clients.import'))->assertForbidden();
    }

    public function test_comma_csv_reports_duplicate_and_invalid_rows_before_import(): void
    {
        $admin = User::factory()->create();
        $csv = "Nome,Email,Partita IVA,Termini pagamento\nGamma,gamma@example.test,12345,30\nGamma,gamma@example.test,12345,30\nDelta,non-valida,99999,30\n";

        $this->actingAsWorkflowAdmin($admin)->postJson(route('clients.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('clienti.csv', $csv),
        ])->assertOk()->assertJsonPath('count', 1)->assertJsonCount(2, 'issues');
        $this->assertDatabaseCount('clients', 0);
    }
}
