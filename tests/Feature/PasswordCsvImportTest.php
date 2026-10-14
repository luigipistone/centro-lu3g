<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_csv_into_owned_personal_vault_and_skips_duplicate_titles(): void
    {
        $user = User::factory()->create();
        $this->role($user);
        $vaultId = $this->vault($user);
        DB::table('password_items')->insert([
            'id' => (string) Str::uuid(),
            'password_vault_id' => $vaultId,
            'title' => 'Già presente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $csv = "\xEF\xBB\xBFTitolo;Nome Utente;Password;URL;Note\n"
            . "MIO SITO;utente@example.it;Segreto!123;https://example.it;Una nota\n"
            . "mio sito;altro;Diversa;;\n"
            . "Già presente;admin;Altro;;\n";

        $this->actingAs($user)->post(route('passwords.import.store'), [
            'password_vault_id' => $vaultId,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ])->assertRedirect(route('passwords.import'));

        $item = DB::table('password_items')->where('password_vault_id', $vaultId)->where('title', 'Mio Sito')->first();
        $this->assertNotNull($item);
        $this->assertSame('utente@example.it', $item->username);
        $this->assertSame('https://example.it', $item->url);
        $this->assertSame('Una nota', $item->notes);
        $this->assertSame('Segreto!123', Crypt::decryptString($item->encrypted_password));
        $this->assertSame(2, DB::table('password_items')->where('password_vault_id', $vaultId)->count());
    }

    public function test_import_rejects_shared_and_other_users_vaults(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->role($user);
        $shared = $this->vault($user, 'shared');
        $ownedByOther = $this->vault($other);

        foreach ([$shared, $ownedByOther] as $vaultId) {
            $this->actingAs($user)->post(route('passwords.import.store'), [
                'password_vault_id' => $vaultId,
                'file' => UploadedFile::fake()->createWithContent('import.csv', "Titolo,Nome utente,Password\nTest,user,secret\n"),
            ])->assertForbidden();
        }

        $this->assertSame(0, DB::table('password_items')->count());
    }

    public function test_import_requires_expected_columns(): void
    {
        $user = User::factory()->create();
        $this->role($user);
        $vaultId = $this->vault($user);

        $this->actingAs($user)->post(route('passwords.import.store'), [
            'password_vault_id' => $vaultId,
            'file' => UploadedFile::fake()->createWithContent('import.csv', "Titolo,Password\nTest,secret\n"),
        ])->assertSessionHasErrors('file');
    }

    private function role(User $user): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'editor']);
    }

    private function vault(User $user, string $visibility = 'personal'): string
    {
        $id = (string) Str::uuid();
        DB::table('password_vaults')->insert([
            'id' => $id,
            'name' => 'Cassaforte test',
            'visibility' => $visibility,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
