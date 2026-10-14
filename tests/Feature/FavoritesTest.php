<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
    }

    public function test_employee_can_create_personal_favorites_but_cannot_share_them(): void
    {
        $employee = User::factory()->create();
        $this->role($employee, 'editor');

        $this->actingAs($employee)->post(route('favorites.folders.store'), ['name' => 'Risorse', 'visibility' => 'personal'])->assertRedirect();
        $folderId = DB::table('favorite_folders')->where('created_by', $employee->id)->value('id');
        $this->actingAs($employee)->post(route('favorites.items.store'), [
            'folder_id' => $folderId, 'title' => 'Sito', 'url' => 'https://example.com',
        ])->assertRedirect();
        $this->assertDatabaseHas('favorite_items', ['folder_id' => $folderId, 'title' => 'Sito']);

        $this->actingAs($employee)->post(route('favorites.folders.store'), ['name' => 'Team', 'visibility' => 'shared'])->assertForbidden();
        $this->actingAs($employee)->post(route('favorites.items.store'), [
            'folder_id' => $folderId, 'title' => 'Script', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');
    }

    public function test_shared_folder_is_visible_only_to_recipients_and_superadmin(): void
    {
        $admin = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $superadmin = User::factory()->create();
        $this->role($admin, 'admin');
        $this->role($recipient, 'editor');
        $this->role($outsider, 'editor');
        $this->role($superadmin, 'superadmin');

        $this->actingAs($admin)->post(route('favorites.folders.store'), [
            'name' => 'Team', 'visibility' => 'shared', 'user_ids' => [$recipient->id],
        ])->assertRedirect();
        $folderId = DB::table('favorite_folders')->where('name', 'Team')->value('id');
        $this->actingAs($admin)->post(route('favorites.items.store'), [
            'folder_id' => $folderId, 'title' => 'Portale', 'url' => 'https://example.com',
        ])->assertRedirect();

        $this->actingAs($recipient)->get(route('favorites.index'))->assertOk()->assertSee('Portale');
        $this->actingAs($recipient)->get(route('favorites.folders.show', $folderId))->assertOk()->assertSee('Portale');
        $this->actingAs($outsider)->get(route('favorites.index'))->assertOk()->assertDontSee('Portale');
        $this->actingAs($outsider)->get(route('favorites.folders.show', $folderId))->assertNotFound();
        $this->actingAs($outsider)->delete(route('favorites.folders.destroy', $folderId))->assertNotFound();
        $this->actingAs($superadmin)->get(route('favorites.index'))->assertOk()->assertSee('Portale');
    }

    public function test_manager_cannot_see_another_users_personal_folder(): void
    {
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($manager, 'admin');
        $this->role($employee, 'editor');
        $this->actingAs($employee)->post(route('favorites.folders.store'), ['name' => 'Privata', 'visibility' => 'personal']);
        $folderId = DB::table('favorite_folders')->where('name', 'Privata')->value('id');
        $this->actingAs($manager)->get(route('favorites.index'))->assertOk()->assertDontSee('Privata');
        $this->actingAs($manager)->get(route('favorites.folders.show', $folderId))->assertNotFound();
        $this->actingAs($manager)->delete(route('favorites.folders.destroy', $folderId))->assertNotFound();
    }

    public function test_disabled_section_blocks_employee_but_not_manager(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->create();
        $this->role($employee, 'editor');
        $this->role($manager, 'admin');
        DB::table('section_availability')->insert(['section_key' => 'favorites', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($employee)->get(route('favorites.index'))->assertStatus(503);
        $this->actingAs($manager)->get(route('favorites.index'))->assertOk();
    }

    public function test_favorite_groups_are_independent_and_control_folder_access(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $this->role($manager, 'admin');
        $this->role($member, 'editor');
        $this->role($outsider, 'editor');

        $this->actingAs($manager)->post(route('favorites.groups.store'), [
            'name' => 'Team creativo', 'description' => 'Risorse condivise', 'user_ids' => [$member->id],
        ])->assertRedirect();
        $groupId = DB::table('favorite_groups')->where('name', 'Team creativo')->value('id');
        $this->assertNotNull($groupId);
        $this->assertDatabaseMissing('password_groups', ['id' => $groupId]);
        $this->assertDatabaseHas('favorite_group_user', ['group_id' => $groupId, 'user_id' => $member->id]);

        $this->actingAs($manager)->post(route('favorites.folders.store'), [
            'name' => 'Link team', 'visibility' => 'shared', 'group_ids' => [$groupId],
        ])->assertRedirect();
        $folderId = DB::table('favorite_folders')->where('name', 'Link team')->value('id');
        $this->assertDatabaseHas('favorite_folder_team', ['folder_id' => $folderId, 'group_id' => $groupId]);
        $this->actingAs($member)->get(route('favorites.folders.show', $folderId))->assertOk();
        $this->actingAs($outsider)->get(route('favorites.folders.show', $folderId))->assertNotFound();
        $this->actingAs($member)->post(route('favorites.groups.store'), ['name' => 'Non autorizzato'])->assertForbidden();

        $this->actingAs($manager)->put(route('favorites.groups.update', $groupId), [
            'name' => 'Team creativo', 'user_ids' => [$outsider->id],
        ])->assertRedirect();
        $this->actingAs($member)->get(route('favorites.folders.show', $folderId))->assertNotFound();
        $this->actingAs($outsider)->get(route('favorites.folders.show', $folderId))->assertOk();
    }
}
