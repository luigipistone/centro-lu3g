<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FigmaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_save_figma_settings_with_encrypted_token(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        $this->actingAs($superadmin)
            ->put(route('settings.figma.update'), [
                'team_id' => '123456789',
                'token' => 'figma-secret-token',
            ])
            ->assertSessionHasNoErrors();

        $settings = DB::table('figma_settings')->first();

        $this->assertSame('123456789', $settings->team_id);
        $this->assertNotSame('figma-secret-token', $settings->encrypted_token);
        $this->assertSame('figma-secret-token', Crypt::decryptString($settings->encrypted_token));
        $this->assertSame(now('Europe/Rome')->addDays(90)->toDateString(), $settings->token_expires_at);
    }

    public function test_admin_can_load_figma_projects_from_configured_team(): void
    {
        Http::fake([
            'api.figma.com/v1/teams/123456789/projects' => Http::response([
                'projects' => [['id' => 'project-1', 'name' => 'Sito LU3G']],
            ]),
        ]);

        $admin = $this->userWithRole('admin');
        DB::table('figma_settings')->insert([
            'id' => (string) Str::uuid(),
            'team_id' => '123456789',
            'encrypted_token' => Crypt::encryptString('figma-secret-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('figma.projects'))
            ->assertOk()
            ->assertJsonPath('projects.0.name', 'Sito LU3G');

        Http::assertSent(fn ($request) => $request->hasHeader('X-Figma-Token', 'figma-secret-token'));
    }

    public function test_admin_can_load_files_for_a_figma_project(): void
    {
        Http::fake([
            'api.figma.com/v1/projects/project-1/files' => Http::response([
                'files' => [[
                    'key' => 'file-key',
                    'name' => 'Homepage',
                    'thumbnail_url' => 'https://figma.example/thumbnail.png',
                    'last_modified' => '2026-07-23T10:00:00Z',
                ]],
            ]),
        ]);

        $admin = $this->userWithRole('admin');
        DB::table('figma_settings')->insert([
            'id' => (string) Str::uuid(),
            'team_id' => '123456789',
            'encrypted_token' => Crypt::encryptString('figma-secret-token'),
            'token_expires_at' => now()->addDays(90),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('figma.project-files', 'project-1'))
            ->assertOk()
            ->assertJsonPath('files.0.key', 'file-key')
            ->assertJsonPath('files.0.name', 'Homepage');
    }

    public function test_figma_iso_timestamp_is_normalized_when_linking_a_project(): void
    {
        $admin = $this->userWithRole('admin');
        $serviceId = (string) Str::uuid();
        $projectId = (string) Str::uuid();

        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'Web',
            'color' => '#2563eb',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Sito cliente',
            'service_id' => $serviceId,
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('projects.update', $projectId), [
                'name' => 'Sito cliente',
                'service_id' => $serviceId,
                'status' => 'active',
                'color' => '#2563eb',
                'figma_url' => 'https://www.figma.com/file/file-key',
                'figma_project_id' => 'project-1',
                'figma_file_key' => 'file-key',
                'figma_file_name' => 'Homepage',
                'figma_thumbnail_url' => 'https://figma.example/thumbnail.png',
                'figma_last_modified_at' => '2026-06-03T12:31:24Z',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'figma_file_key' => 'file-key',
            'figma_last_modified_at' => '2026-06-03 14:31:24',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }
}
