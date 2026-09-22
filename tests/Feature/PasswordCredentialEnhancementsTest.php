<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordCredentialEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_is_required_and_structured_security_data_is_saved(): void
    {
        $user = User::factory()->create();
        $this->role($user, 'editor');
        $vaultId = $this->vault($user, 'Generale');

        $this->actingAs($user)->post(route('passwords.items.store'), [
            'password_vault_id' => $vaultId,
            'title' => 'WordPress cliente',
            'password' => 'PasswordSicura!2026',
            'credential_status' => 'active',
            'mfa_status' => 'enabled',
        ])->assertSessionHasErrors('category');

        $this->actingAs($user)->post(route('passwords.items.store'), [
            'password_vault_id' => $vaultId,
            'title' => 'WordPress cliente',
            'category' => 'wordpress',
            'subcategory' => 'WordPress',
            'category_data' => ['role' => 'Amministratore', 'site' => 'Sito principale', 'ignored' => 'no'],
            'username' => 'admin',
            'password' => 'PasswordSicura!2026',
            'credential_status' => 'active',
            'mfa_status' => 'enabled',
        ])->assertRedirect();

        $item = DB::table('password_items')->where('title', 'WordPress cliente')->first();
        $this->assertSame('wordpress', $item->category);
        $this->assertSame(['role' => 'Amministratore', 'site' => 'Sito principale'], json_decode($item->category_data, true));
        $this->assertSame(19, $item->password_length);
        $this->assertNotNull($item->password_fingerprint);
        $this->assertSame('PasswordSicura!2026', Crypt::decryptString($item->encrypted_password));
    }

    public function test_administration_vault_is_exclusive_to_superadmin(): void
    {
        $superadmin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($employee, 'editor');
        $vaultId = $this->vault($superadmin, 'Amministrazione', 'shared');
        $itemId = (string) Str::uuid();
        DB::table('password_items')->insert([
            'id' => $itemId,
            'password_vault_id' => $vaultId,
            'title' => 'Segreta',
            'category' => 'other',
            'credential_status' => 'active',
            'mfa_status' => 'unknown',
            'encrypted_password' => Crypt::encryptString('segreta'),
            'created_by' => $superadmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)->post(route('passwords.items.reveal', $itemId))->assertForbidden();
        $this->actingAs($superadmin)->post(route('passwords.items.reveal', $itemId))->assertOk();
    }

    public function test_compromised_page_does_not_perform_external_checks_during_render(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->role($user, 'superadmin');
        $vaultId = $this->vault($user, 'Generale');
        DB::table('password_items')->insert([
            'id' => (string) Str::uuid(),
            'password_vault_id' => $vaultId,
            'title' => 'Da verificare',
            'category' => 'other',
            'credential_status' => 'active',
            'mfa_status' => 'unknown',
            'encrypted_password' => Crypt::encryptString('password'),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('passwords.compromised'))->assertOk();
        Http::assertNothingSent();
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
    }

    private function vault(User $user, string $name, string $visibility = 'personal'): string
    {
        $id = (string) Str::uuid();
        DB::table('password_vaults')->insert([
            'id' => $id,
            'name' => $name,
            'visibility' => $visibility,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
