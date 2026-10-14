<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordVaultOverviewPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_visibility_defaults_on_and_is_saved_per_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->role($first, 'superadmin');
        $this->role($second, 'superadmin');
        $vaultId = $this->vault($first, 'shared');

        $this->actingAs($first)->get(route('passwords.vaults.show', $vaultId))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('selectedVault.show_in_overview', true)->etc());

        $this->actingAs($first)->patch(route('passwords.vaults.overview-visibility', $vaultId), [
            'show_in_overview' => false,
        ])->assertRedirect();

        $this->actingAs($first)->get(route('passwords.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('vaults.0.show_in_overview', false)->etc());
        $this->actingAs($second)->get(route('passwords.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('vaults.0.show_in_overview', true)->etc());

        $this->actingAs($first)->patch(route('passwords.vaults.overview-visibility', $vaultId), [
            'show_in_overview' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('password_vault_user_preferences', [
            'password_vault_id' => $vaultId,
            'user_id' => $first->id,
            'show_in_overview' => true,
        ]);
    }

    public function test_non_editor_can_open_accessible_vault_but_cannot_change_its_settings(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        foreach ([$owner, $viewer, $outsider] as $user) {
            $this->role($user, 'editor');
        }
        $vaultId = $this->vault($owner, 'shared');
        DB::table('password_vault_user')->insert([
            'id' => (string) Str::uuid(),
            'password_vault_id' => $vaultId,
            'user_id' => $viewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)->get(route('passwords.vaults.show', $vaultId))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('selectedVault.can_edit', false)->etc());
        $this->actingAs($viewer)->patch(route('passwords.vaults.overview-visibility', $vaultId), [
            'show_in_overview' => false,
        ])->assertRedirect();
        $this->actingAs($viewer)->put(route('passwords.vaults.update', $vaultId), [
            'name' => 'Modifica non consentita',
        ])->assertForbidden();

        $this->actingAs($outsider)->get(route('passwords.vaults.show', $vaultId))->assertNotFound();
        $this->actingAs($outsider)->patch(route('passwords.vaults.overview-visibility', $vaultId), [
            'show_in_overview' => false,
        ])->assertForbidden();
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
    }

    private function vault(User $user, string $visibility): string
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
