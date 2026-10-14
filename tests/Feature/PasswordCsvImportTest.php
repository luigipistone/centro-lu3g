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
            'username' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $csv = "\xEF\xBB\xBFTitolo;Nome Utente;Password;URL;Note\n"
            . "MIO SITO;utente@example.it;Segreto!123;https://example.it;Una nota\n"
            . "mio sito;utente@example.it;Diversa;https://example.it;\n"
            . "Già presente;admin;Altro;;\n";

        $this->actingAs($user)->post(route('passwords.import.store'), [
            'password_vault_id' => $vaultId,
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ])->assertRedirect(route('passwords.import'))
            ->assertSessionHas('status', '1 password importate. 2 già presenti, 0 righe non valide.');

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

    public function test_superadmin_imports_headerless_export_into_shared_vault_without_losing_homonyms(): void
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'superadmin']);
        $vaultId = $this->vault($user, 'shared');
        $csv = "user1,note uno,Segreto1,,Accesso,STESSO TITOLO,https://example.it,\n"
            . "user2,note due,Segreto2,,Accesso,STESSO TITOLO,https://example.it,\n"
            . "user2,note due,Segreto2,,Accesso,STESSO TITOLO,https://example.it,\n"
            . ",nota privata,,,Nota sicura,Da escludere,,\n";

        $this->actingAs($user)->post(route('passwords.import.store'), [
            'password_vault_id' => $vaultId,
            'file' => UploadedFile::fake()->createWithContent('export.csv', $csv),
        ])->assertRedirect(route('passwords.import'))
            ->assertSessionHas('status', '2 password importate. 1 già presenti, 1 righe non valide.');

        $items = DB::table('password_items')->where('password_vault_id', $vaultId)->orderBy('username')->get();
        $this->assertCount(2, $items);
        $this->assertSame('Stesso Titolo', $items[0]->title);
        $this->assertSame('user1', $items[0]->username);
        $this->assertSame('note uno', $items[0]->notes);
        $this->assertSame('Segreto1', Crypt::decryptString($items[0]->encrypted_password));
        $this->assertSame('user2', $items[1]->username);
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
