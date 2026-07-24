<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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

    public function test_admin_can_analyze_figma_colors_and_typography_without_ai(): void
    {
        Http::fake([
            'api.figma.com/v1/files/file-key/meta' => Http::response([
                'file' => ['name' => 'Homepage', 'editorType' => 'figma'],
            ]),
            'api.figma.com/v1/files/file-key' => Http::response([
                'document' => [
                    'type' => 'DOCUMENT',
                    'children' => [[
                        'type' => 'FRAME',
                        'fills' => [['type' => 'SOLID', 'color' => ['r' => 0.1, 'g' => 0.4, 'b' => 0.9]]],
                        'children' => [
                            [
                                'type' => 'TEXT',
                                'characters' => 'Titolo principale',
                                'fills' => [['type' => 'SOLID', 'color' => ['r' => 0.05, 'g' => 0.06, 'b' => 0.08]]],
                                'style' => ['fontFamily' => 'Inter', 'fontWeight' => 700, 'fontSize' => 48],
                            ],
                            [
                                'type' => 'TEXT',
                                'characters' => str_repeat('Testo del contenuto ', 8),
                                'fills' => [['type' => 'SOLID', 'color' => ['r' => 0.12, 'g' => 0.14, 'b' => 0.18]]],
                                'style' => ['fontFamily' => 'Inter', 'fontWeight' => 400, 'fontSize' => 16],
                            ],
                            [
                                'type' => 'RECTANGLE',
                                'fills' => [['type' => 'SOLID', 'color' => ['r' => 0.95, 'g' => 0.3, 'b' => 0.2]]],
                            ],
                            [
                                'type' => 'TEXT',
                                'characters' => 'Etichetta',
                                'style' => ['fontFamily' => 'Roboto Mono', 'fontWeight' => 500, 'fontSize' => 12],
                            ],
                        ],
                    ]],
                ],
            ]),
        ]);

        $admin = $this->userWithRole('admin');
        $projectId = $this->configuredWebProject($admin);

        $response = $this->actingAs($admin)
            ->postJson(route('projects.figma-design-system.analyze', $projectId))
            ->assertOk();

        $response->assertJsonPath('design_system.colors.primary', '#1A66E6');
        $response->assertJsonPath('design_system.typography.primary.family', 'Inter');
        $response->assertJsonPath('design_system.typography.available.1.family', 'Roboto Mono');
        $response->assertJsonPath('design_system.typography.available.1.weights.0', 500);
        $this->assertDatabaseHas('figma_design_systems', [
            'project_id' => $projectId,
            'status' => 'analyzed',
        ]);
    }

    public function test_figma_sites_file_returns_a_clear_design_analysis_error(): void
    {
        Http::fake([
            'api.figma.com/v1/files/file-key/meta' => Http::response([
                'file' => ['name' => 'Sito pubblicato', 'editorType' => 'sites'],
            ]),
        ]);

        $admin = $this->userWithRole('admin');
        $projectId = $this->configuredWebProject($admin);

        $this->actingAs($admin)
            ->postJson(route('projects.figma-design-system.analyze', $projectId))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Il file collegato è di tipo Figma Sites. Figma consente l’analisi di colori e font solo sui file Figma Design.',
            );
    }

    public function test_admin_can_apply_analyzed_design_system_to_completed_wordpress_site(): void
    {
        Process::fake([
            '*' => Process::result(output: 'Design system applicato.'),
        ]);

        $admin = $this->userWithRole('admin');
        $projectId = $this->configuredWebProject($admin);
        $clientId = DB::table('projects')->where('id', $projectId)->value('client_id');
        DB::table('figma_design_systems')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'figma_file_key' => 'file-key',
            'colors' => json_encode(['primary' => '#2563EB', 'secondary' => '#0F172A', 'text' => '#334155', 'accent' => '#F43F5E']),
            'typography' => json_encode([
                'primary' => ['family' => 'Inter', 'weight' => 700],
                'secondary' => ['family' => 'Inter', 'weight' => 600],
                'text' => ['family' => 'Inter', 'weight' => 400],
                'accent' => ['family' => 'Inter', 'weight' => 500],
            ]),
            'status' => 'analyzed',
            'analyzed_by' => $admin->id,
            'analyzed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('wordpress_provisionings')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'client_id' => $clientId,
            'folder_slug' => 'cliente-figma',
            'site_url' => 'https://testing.lu3g.com/cliente-figma',
            'status' => 'completed',
            'progress' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'colors' => ['primary' => '#2563EB', 'secondary' => '#0F172A', 'text' => '#334155', 'accent' => '#F43F5E'],
            'typography' => [
                'primary' => ['family' => 'Inter', 'weight' => 700],
                'secondary' => ['family' => 'Inter', 'weight' => 600],
                'text' => ['family' => 'Inter', 'weight' => 400],
                'accent' => ['family' => 'Inter', 'weight' => 500],
            ],
        ];

        $this->actingAs($admin)
            ->postJson(route('projects.figma-design-system.apply', $projectId), $payload)
            ->assertOk()
            ->assertJsonPath('design_system.status', 'applied');

        Process::assertRan(fn ($process) => in_array('apply-elementor-design', $process->command, true));
        $this->assertDatabaseHas('figma_design_systems', [
            'project_id' => $projectId,
            'status' => 'applied',
        ]);
    }

    public function test_design_system_from_a_previously_selected_file_cannot_be_loaded_or_applied(): void
    {
        Process::fake();
        $admin = $this->userWithRole('admin');
        $projectId = $this->configuredWebProject($admin);
        $clientId = DB::table('projects')->where('id', $projectId)->value('client_id');
        DB::table('figma_design_systems')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'figma_file_key' => 'old-file-key',
            'colors' => json_encode(['primary' => '#2563EB', 'secondary' => '#0F172A', 'text' => '#334155', 'accent' => '#F43F5E']),
            'typography' => json_encode([
                'primary' => ['family' => 'Inter', 'weight' => 700],
                'secondary' => ['family' => 'Inter', 'weight' => 600],
                'text' => ['family' => 'Inter', 'weight' => 400],
                'accent' => ['family' => 'Inter', 'weight' => 500],
            ]),
            'status' => 'analyzed',
            'analyzed_by' => $admin->id,
            'analyzed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('wordpress_provisionings')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'client_id' => $clientId,
            'folder_slug' => 'cliente-figma',
            'site_url' => 'https://testing.lu3g.com/cliente-figma',
            'status' => 'completed',
            'progress' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'colors' => ['primary' => '#2563EB', 'secondary' => '#0F172A', 'text' => '#334155', 'accent' => '#F43F5E'],
            'typography' => [
                'primary' => ['family' => 'Inter', 'weight' => 700],
                'secondary' => ['family' => 'Inter', 'weight' => 600],
                'text' => ['family' => 'Inter', 'weight' => 400],
                'accent' => ['family' => 'Inter', 'weight' => 500],
            ],
        ];

        $this->actingAs($admin)
            ->getJson(route('projects.figma-design-system.show', $projectId))
            ->assertOk()
            ->assertJsonPath('design_system', null);

        $this->actingAs($admin)
            ->postJson(route('projects.figma-design-system.apply', $projectId), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Analizza prima il design system Figma.');

        Process::assertNothingRan();
    }

    private function configuredWebProject(User $creator): string
    {
        DB::table('figma_settings')->insert([
            'id' => (string) Str::uuid(),
            'team_id' => '123456789',
            'encrypted_token' => Crypt::encryptString('figma-secret-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $serviceId = (string) Str::uuid();
        $clientId = (string) Str::uuid();
        $projectId = (string) Str::uuid();
        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'Web',
            'color' => '#2563eb',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente Figma',
            'created_by' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Sito Figma',
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'figma_file_key' => 'file-key',
            'figma_file_name' => 'Homepage',
            'figma_url' => 'https://www.figma.com/file/file-key',
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $projectId;
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
