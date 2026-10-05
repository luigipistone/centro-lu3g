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

    public function test_manager_can_open_a_user_profile_page_with_field_permissions(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $this->role($admin, 'admin');
        $this->role($target, 'editor');

        $this
            ->actingAs($admin)
            ->get(route('users.show', $target->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centro/Show')
                ->where('related.fieldAccess.operational_update', true)
                ->where('related.fieldAccess.contract_view', true)
                ->where('related.fieldAccess.contract_update', false)
                ->where('related.fieldAccess.security_update', false)
            );
    }

    public function test_autosave_updates_only_personal_profile_fields(): void
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
            'role' => 'guest',
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $target->id,
            'full_name' => 'Marco Rossi',
            'phone' => '+39 02 123456',
            'bio' => 'Profilo operativo interno.',
        ]);
        $this->assertNull(DB::table('profiles')->where('user_id', $target->id)->value('job_title'));
    }

    public function test_personal_details_are_saved_and_available_in_the_user_profile(): void
    {
        $superadmin = User::factory()->create();
        $target = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($target, 'editor');

        $this->actingAs($superadmin)->put(route('users.update', $target->id), [
            'name' => 'Giulia Verdi', 'email' => 'giulia@example.test',
            'first_name' => 'Giulia', 'last_name' => 'Verdi',
            'fiscal_code' => 'vrdglu90a41f205x', 'birth_date' => '1990-01-01',
            'birth_place' => 'Milano', 'gender' => 'female',
            'personal_email' => 'giulia.personale@example.test', 'residence_place' => 'Monza',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profiles', [
            'user_id' => $target->id, 'fiscal_code' => 'VRDGLU90A41F205X',
            'birth_date' => '1990-01-01', 'birth_place' => 'Milano',
            'gender' => 'female', 'personal_email' => 'giulia.personale@example.test',
            'residence_place' => 'Monza',
        ]);
        $this->actingAs($superadmin)->get(route('users.show', $target->id))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('record.fiscal_code', 'VRDGLU90A41F205X')
                ->where('record.residence_place', 'Monza')
            );
    }

    public function test_manager_and_superadmin_can_export_a_user_profile_but_employee_cannot(): void
    {
        $superadmin = User::factory()->create();
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $target = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($manager, 'admin');
        $this->role($employee, 'editor');
        $this->role($target, 'editor');
        DB::table('profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $target->id,
            'full_name' => $target->name, 'fiscal_code' => 'VRDGLU90A41F205X',
            'birth_place' => 'Milano', 'residence_place' => 'Monza',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $xlsx = $this->actingAs($manager)->get(route('users.export', [$target->id, 'xlsx']));
        $xlsx->assertOk()->assertDownload();
        $zip = new \ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'test-profile-xlsx-');
        file_put_contents($path, file_get_contents($xlsx->baseResponse->getFile()->getPathname()));
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('VRDGLU90A41F205X', $sheet);
        $this->assertStringContainsString('Monza', $sheet);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id, 'subject_id' => $target->id,
            'route_name' => 'users.export', 'action' => 'esportazione',
        ]);

        $pdf = $this->actingAs($superadmin)->get(route('users.export', [$target->id, 'pdf']));
        $pdf->assertOk()->assertDownload();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($employee)->get(route('users.export', [$target->id, 'xlsx']))->assertForbidden();
    }

    public function test_superadmin_can_upload_an_avatar_for_another_user(): void
    {
        Storage::fake('local');

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
        Storage::disk('local')->assertExists('avatars/'.basename($avatarUrl));
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
