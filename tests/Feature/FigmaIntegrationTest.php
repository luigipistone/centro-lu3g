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
