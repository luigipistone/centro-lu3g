<?php

namespace Tests\Feature;

use App\Jobs\ProvisionWordPress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_wordpress_provisioning_for_a_project_client(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');
        [$clientId, $projectId] = $this->projectWithClient($admin);

        $this->actingAs($admin)
            ->post(route('projects.wordpress-provisioning.store', $projectId))
            ->assertRedirect();

        $this->assertDatabaseHas('wordpress_provisionings', [
            'project_id' => $projectId,
            'client_id' => $clientId,
            'folder_slug' => 'cliente-provisioning',
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProvisionWordPress::class, fn (ProvisionWordPress $job) => filled($job->provisioningId));
    }

    public function test_non_admin_cannot_queue_wordpress_provisioning(): void
    {
        Queue::fake();

        $editor = $this->userWithRole('editor');
        [, $projectId] = $this->projectWithClient($editor);

        $this->actingAs($editor)
            ->post(route('projects.wordpress-provisioning.store', $projectId))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_project_without_client_cannot_be_provisioned(): void
    {
        Queue::fake();

        $admin = $this->userWithRole('admin');
        $projectId = (string) Str::uuid();

        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Progetto senza cliente',
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('projects.show', $projectId))
            ->post(route('projects.wordpress-provisioning.store', $projectId))
            ->assertRedirect(route('projects.show', $projectId))
            ->assertSessionHasErrors('project');

        Queue::assertNothingPushed();
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

    private function projectWithClient(User $creator): array
    {
        $clientId = (string) Str::uuid();
        $projectId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente Provisioning',
            'created_by' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Nuovo sito cliente',
            'client_id' => $clientId,
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$clientId, $projectId];
    }
}
