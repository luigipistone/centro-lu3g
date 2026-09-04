<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_dashboard_widget_layout(): void
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson('/dashboard/widgets', [
                'widgets' => [
                    ['widget_type' => 'upcoming_tasks', 'position' => 0, 'col_span' => 3, 'visible' => true],
                    ['widget_type' => 'stat_clients', 'position' => 1, 'col_span' => 1, 'visible' => true],
                    ['widget_type' => 'notes', 'position' => 2, 'col_span' => 2, 'visible' => true],
                    ['widget_type' => 'urgent_tasks', 'position' => 3, 'col_span' => 2, 'visible' => false],
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('dashboard_widgets', [
            'user_id' => $user->id,
            'widget_type' => 'upcoming_tasks',
            'position' => 0,
            'col_span' => 3,
            'visible' => true,
        ]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'user_id' => $user->id,
            'widget_type' => 'urgent_tasks',
            'position' => 3,
            'col_span' => 2,
            'visible' => false,
        ]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'user_id' => $user->id,
            'widget_type' => 'notes',
            'position' => 2,
            'col_span' => 2,
            'visible' => true,
        ]);
    }

    public function test_user_can_save_dashboard_note(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patchJson('/dashboard/note', [
                'html' => '<h1>Promemoria</h1><p><strong>Chiamare cliente</strong></p><script>alert(1)</script>',
            ]);

        $response->assertOk();

        $note = DB::table('user_notes')->where('user_id', $user->id)->first();

        $this->assertNotNull($note);
        $content = json_decode($note->content, true);

        $this->assertStringContainsString('<h1>Promemoria</h1>', $content['html']);
        $this->assertStringContainsString('<strong>Chiamare cliente</strong>', $content['html']);
        $this->assertStringNotContainsString('<script>', $content['html']);
    }

    public function test_user_can_save_personal_dashboard_widget_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/dashboard/widgets/quick_links/settings', [
                'links' => [
                    ['label' => 'Laravel', 'url' => 'https://laravel.com'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('settings.links.0.label', 'Laravel');

        $this->assertDatabaseHas('dashboard_widget_settings', [
            'user_id' => $user->id,
            'widget_type' => 'quick_links',
        ]);

        $this->actingAs($user)
            ->patchJson('/dashboard/widgets/weather/settings', [
                'city' => 'Milano',
                'latitude' => 45.4642,
                'longitude' => 9.19,
            ])
            ->assertOk()
            ->assertJsonPath('settings.city', 'Milano');
    }

    public function test_dashboard_shows_only_active_projects_assigned_to_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $assignedProjectId = (string) str()->uuid();
        $otherProjectId = (string) str()->uuid();
        $archivedProjectId = (string) str()->uuid();

        DB::table('projects')->insert([
            [
                'id' => $assignedProjectId,
                'name' => 'Portale assegnato',
                'status' => 'active',
                'color' => '#2563eb',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $otherProjectId,
                'name' => 'Portale non assegnato',
                'status' => 'active',
                'color' => '#2563eb',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $archivedProjectId,
                'name' => 'Portale archiviato',
                'status' => 'archived',
                'color' => '#2563eb',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('project_followers')->insert([
            [
                'id' => (string) str()->uuid(),
                'project_id' => $assignedProjectId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) str()->uuid(),
                'project_id' => $otherProjectId,
                'user_id' => $otherUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) str()->uuid(),
                'project_id' => $archivedProjectId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('activeProjects.0.id', $assignedProjectId)
            ->where('activeProjects.0.name', 'Portale assegnato')
            ->where('activeProjects', fn ($projects) => count($projects) === 1)
            ->where('dashboardWidgets', fn ($widgets) => collect($widgets)->where('widget_type', 'stat_projects')->isEmpty())
        );
    }

    public function test_legacy_project_stat_widget_is_replaced_by_active_project_list(): void
    {
        $user = User::factory()->create();

        DB::table('dashboard_widgets')->insert([
            [
                'id' => (string) str()->uuid(),
                'user_id' => $user->id,
                'widget_type' => 'stat_projects',
                'position' => 1,
                'size' => 'small',
                'col_span' => 1,
                'visible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) str()->uuid(),
                'user_id' => $user->id,
                'widget_type' => 'active_projects',
                'position' => 6,
                'size' => 'small',
                'col_span' => 1,
                'visible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('dashboardWidgets', fn ($widgets) => collect($widgets)->where('widget_type', 'stat_projects')->isEmpty())
            ->where('dashboardWidgets', fn ($widgets) => collect($widgets)
                ->where('widget_type', 'active_projects')
                ->where('visible', true)
                ->where('position', 1)
                ->isNotEmpty())
        );
    }
}
