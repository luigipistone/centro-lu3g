<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_a_user(): void
    {
        $superadmin = User::factory()->create();
        $this->role($superadmin, 'superadmin');

        $this
            ->actingAs($superadmin)
            ->post(route('users.store'), [
                'name' => 'Nuovo Utente',
                'email' => 'nuovo.utente@example.test',
                'role' => 'editor',
                'password' => 'Password-sicura-123',
            ])
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'nuovo.utente@example.test')->firstOrFail();

        $this->assertSame('Nuovo Utente', $user->name);
        $this->assertTrue(Hash::check('Password-sicura-123', $user->password));
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role' => 'editor']);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'full_name' => 'Nuovo Utente']);
    }

    public function test_superadmin_can_open_a_user_profile_page(): void
    {
        $superadmin = User::factory()->create();
        $target = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($target, 'editor');

        $this
            ->actingAs($superadmin)
            ->get(route('users.show', $target->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centro/Show')
                ->where('section', 'users')
                ->where('record.id', $target->id)
            );
    }

    public function test_non_superadmin_cannot_open_a_user_profile_page(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $this->role($admin, 'admin');
        $this->role($target, 'editor');

        $this
            ->actingAs($admin)
            ->get(route('users.show', $target->id))
            ->assertForbidden();
    }

    public function test_superadmin_can_autosave_user_profile_fields(): void
    {
        $superadmin = User::factory()->create();
        $target = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($target, 'guest');

        $this
            ->actingAs($superadmin)
            ->put(route('users.update', $target->id), [
                'name' => 'Marco Rossi',
                'email' => 'marco.rossi@example.test',
                'role' => 'editor',
                'job_title' => 'Project manager',
                'phone' => '+39 02 123456',
                'bio' => 'Profilo operativo interno.',
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        $target->refresh();

        $this->assertSame('Marco Rossi', $target->name);
        $this->assertSame('marco.rossi@example.test', $target->email);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role' => 'editor',
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $target->id,
            'full_name' => 'Marco Rossi',
            'job_title' => 'Project manager',
            'phone' => '+39 02 123456',
            'bio' => 'Profilo operativo interno.',
        ]);
    }

    public function test_superadmin_can_upload_an_avatar_for_another_user(): void
    {
        Storage::fake('public');

        $superadmin = User::factory()->create();
        $target = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($target, 'guest');

        $this
            ->actingAs($superadmin)
            ->post(route('users.avatar.update', $target->id), [
                'avatar' => UploadedFile::fake()->image('avatar.webp', 256, 256),
            ])
            ->assertSessionHasNoErrors();

        $avatarUrl = DB::table('profiles')->where('user_id', $target->id)->value('avatar_url');

        $this->assertNotNull($avatarUrl);
        $this->assertStringStartsWith('/avatars/', $avatarUrl);
        Storage::disk('public')->assertExists('avatars/'.basename($avatarUrl));
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
