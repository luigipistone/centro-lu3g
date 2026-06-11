<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_dashboard_widget_layout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patchJson('/dashboard/widgets', [
                'widgets' => [
                    ['widget_type' => 'upcoming_tasks', 'position' => 0, 'col_span' => 3, 'visible' => true],
                    ['widget_type' => 'stat_clients', 'position' => 1, 'col_span' => 1, 'visible' => true],
                    ['widget_type' => 'urgent_tasks', 'position' => 2, 'col_span' => 2, 'visible' => false],
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
            'position' => 2,
            'col_span' => 2,
            'visible' => false,
        ]);
    }
}
