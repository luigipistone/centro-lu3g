<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalContainersPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_containers_are_owner_only_even_for_superadmin(): void
    {
        $owner = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $owner->id, 'role' => 'superadmin']);
        $vault = (string) Str::uuid();
        $folder = (string) Str::uuid();
        $password = (string) Str::uuid();
        $favorite = (string) Str::uuid();
        DB::table('password_vaults')->insert(['id' => $vault, 'name' => 'PrivateVaultMarker', 'visibility' => 'personal', 'created_by' => $owner->id]);
        DB::table('favorite_folders')->insert(['id' => $folder, 'name' => 'PrivateFolderMarker', 'visibility' => 'personal', 'created_by' => $owner->id]);
        DB::table('password_items')->insert(['id' => $password, 'password_vault_id' => $vault, 'title' => 'PrivatePasswordMarker', 'encrypted_password' => Crypt::encryptString('test-secret'), 'created_by' => $owner->id]);
        DB::table('favorite_items')->insert(['id' => $favorite, 'folder_id' => $folder, 'title' => 'PrivateFavoriteMarker', 'url' => 'https://example.com', 'created_by' => $owner->id]);
        foreach (['superadmin', 'admin', 'editor'] as $role) {
            $other = User::factory()->create();
            DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $other->id, 'role' => $role]);
            // Old direct shares must not override the personal container boundary.
            DB::table('password_vault_user')->insert(['id' => (string) Str::uuid(), 'password_vault_id' => $vault, 'user_id' => $other->id]);
            $this->actingAs($other)->get(route('passwords.index'))->assertOk()->assertDontSee('PrivateVaultMarker')->assertDontSee('PrivatePasswordMarker');
            $this->get(route('passwords.vaults.show', $vault))->assertNotFound();
            $this->postJson(route('passwords.items.reveal', $password))->assertForbidden();
            $this->putJson(route('passwords.items.update', $password), [])->assertForbidden();
            $this->delete(route('passwords.vaults.destroy', $vault))->assertForbidden();
            $this->get(route('favorites.index'))->assertOk()->assertDontSee('PrivateFolderMarker')->assertDontSee('PrivateFavoriteMarker');
            $this->get(route('favorites.folders.show', $folder))->assertNotFound();
            $this->delete(route('favorites.items.destroy', $favorite))->assertNotFound();
            $this->delete(route('favorites.folders.destroy', $folder))->assertNotFound();
        }
        $this->actingAs($owner)->get(route('passwords.index'))->assertOk()->assertSee('PrivatePasswordMarker');
        $this->postJson(route('passwords.items.reveal', $password))->assertOk()->assertJsonPath('password', 'test-secret');
        $this->get(route('favorites.index'))->assertOk()->assertSee('PrivateFavoriteMarker');
    }
}
