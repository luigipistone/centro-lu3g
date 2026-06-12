<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_detail_can_update_main_fields(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $projectId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Centro QA',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Portale vecchio',
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/projects/{$projectId}", [
                'name' => 'Portale Centro LU3G',
                'client_id' => $clientId,
                'status' => 'on_hold',
                'color' => '#16a34a',
                'description' => 'Allineamento progetto da dettaglio.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'Portale Centro LU3G',
            'client_id' => $clientId,
            'status' => 'on_hold',
            'color' => '#16a34a',
            'description' => 'Allineamento progetto da dettaglio.',
        ]);
    }

    public function test_project_followers_can_be_synced_from_detail(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $removedMember = User::factory()->create();
        $projectId = (string) Str::uuid();

        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Progetto membri',
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_followers')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'user_id' => $removedMember->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/projects/{$projectId}/followers", [
                'user_ids' => [$member->id, $member->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_followers', [
            'project_id' => $projectId,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('project_followers', [
            'project_id' => $projectId,
            'user_id' => $removedMember->id,
        ]);
        $this->assertSame(1, DB::table('project_followers')->where('project_id', $projectId)->count());
    }

    public function test_project_update_can_sync_members_with_main_save(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $removedMember = User::factory()->create();
        $projectId = (string) Str::uuid();

        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Progetto membri',
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_followers')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'user_id' => $removedMember->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/projects/{$projectId}", [
                'name' => 'Progetto membri aggiornato',
                'client_id' => null,
                'status' => 'active',
                'color' => '#0891b2',
                'description' => null,
                'user_ids' => [$member->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_followers', [
            'project_id' => $projectId,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('project_followers', [
            'project_id' => $projectId,
            'user_id' => $removedMember->id,
        ]);
    }

    public function test_project_update_without_member_payload_keeps_existing_members(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $projectId = (string) Str::uuid();

        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Progetto membri',
            'status' => 'active',
            'color' => '#2563eb',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_followers')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'user_id' => $member->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/projects/{$projectId}", [
                'name' => 'Progetto senza membri nel payload',
                'client_id' => null,
                'status' => 'active',
                'color' => '#0891b2',
                'description' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_followers', [
            'project_id' => $projectId,
            'user_id' => $member->id,
        ]);
    }
}
