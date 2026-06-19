<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_recurring_task_creates_next_occurrence(): void
    {
        $user = User::factory()->create();
        $taskId = (string) Str::uuid();
        $subtaskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Report social',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'ongoing',
            'due_date' => '2026-06-20',
            'due_time' => '10:00',
            'recurring_enabled' => true,
            'recurring_interval_unit' => 'week',
            'recurring_interval_value' => 1,
            'recurring_mode' => 'fixed',
            'recurring_weekday' => 6,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tasks')->insert([
            'id' => $subtaskId,
            'title' => 'Controllo copy',
            'priority' => 'low',
            'status' => 'todo',
            'task_type' => 'task',
            'parent_task_id' => $taskId,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('task_assignees')->insert([
            'id' => (string) Str::uuid(),
            'task_id' => $taskId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patch("/tasks/{$taskId}/status", ['status' => 'done']);

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'status' => 'done',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $subtaskId,
            'status' => 'done',
        ]);

        $newTask = DB::table('tasks')
            ->where('title', 'Report social')
            ->where('id', '!=', $taskId)
            ->first();

        $this->assertNotNull($newTask);
        $this->assertSame('todo', $newTask->status);
        $this->assertSame('2026-06-27', $newTask->due_date);
        $this->assertSame('ongoing', $newTask->task_type);

        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $newTask->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'parent_task_id' => $newTask->id,
            'title' => 'Controllo copy',
            'status' => 'todo',
        ]);
    }

    public function test_task_can_be_created_with_assignees_and_followers(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $follower = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/tasks', [
                'title' => 'Preparare piano editoriale',
                'task_type' => 'project',
                'status' => 'todo',
                'priority' => 'high',
                'due_date' => '2026-06-30',
                'assignee_ids' => [$assignee->id],
                'follower_ids' => [$follower->id],
            ]);

        $response->assertRedirect();

        $task = DB::table('tasks')->where('title', 'Preparare piano editoriale')->first();

        $this->assertNotNull($task);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);
        $this->assertDatabaseHas('task_followers', [
            'task_id' => $task->id,
            'user_id' => $follower->id,
        ]);
    }

    public function test_task_detail_route_uses_id_and_section_correctly(): void
    {
        $user = User::factory()->create();
        $taskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Task dettaglio',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'ongoing',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get("/tasks/{$taskId}")
            ->assertOk();
    }

    public function test_task_update_route_uses_id_and_section_correctly(): void
    {
        $user = User::factory()->create();
        $taskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Task da modificare',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'ongoing',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/tasks/{$taskId}", [
                'title' => 'Task modificata',
                'task_type' => 'ongoing',
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => '2026-06-12',
                'recurring_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'title' => 'Task modificata',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);
    }

    public function test_repeated_task_autosaves_coalesce_update_notifications(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $taskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Task notifiche',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'project',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('task_assignees')->insert([
            'id' => (string) Str::uuid(),
            'task_id' => $taskId,
            'user_id' => $assignee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/tasks/{$taskId}", [
                'title' => 'Task notifiche aggiornata',
                'task_type' => 'project',
                'status' => 'todo',
                'priority' => 'medium',
                'recurring_enabled' => false,
            ])
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->put("/tasks/{$taskId}", [
                'title' => 'Task notifiche aggiornata',
                'task_type' => 'project',
                'status' => 'todo',
                'priority' => 'high',
                'recurring_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertSame(1, DB::table('notifications')
            ->where('user_id', $assignee->id)
            ->where('task_id', $taskId)
            ->where('type', 'task_updated')
            ->count());

        $this->assertStringContainsString('priorità', DB::table('notifications')
            ->where('user_id', $assignee->id)
            ->where('task_id', $taskId)
            ->where('type', 'task_updated')
            ->value('message'));
    }

    public function test_task_autosave_update_without_people_payload_keeps_people(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $follower = User::factory()->create();
        $taskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Task autosave',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'ongoing',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('task_assignees')->insert([
            'id' => (string) Str::uuid(),
            'task_id' => $taskId,
            'user_id' => $assignee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('task_followers')->insert([
            'id' => (string) Str::uuid(),
            'task_id' => $taskId,
            'user_id' => $follower->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/tasks/{$taskId}", [
                'title' => 'Task autosave aggiornata',
                'task_type' => 'ongoing',
                'status' => 'in_progress',
                'priority' => 'medium',
                'recurring_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $taskId,
            'user_id' => $assignee->id,
        ]);
        $this->assertDatabaseHas('task_followers', [
            'task_id' => $taskId,
            'user_id' => $follower->id,
        ]);
    }

    public function test_task_index_includes_linked_service_information(): void
    {
        $user = User::factory()->create();
        $serviceId = (string) Str::uuid();
        $taskId = (string) Str::uuid();

        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'SEO',
            'color' => '#22c55e',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Ottimizzazione pagine',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'project',
            'service_id' => $serviceId,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get('/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centro/Index')
                ->where('rows.0.id', $taskId)
                ->where('rows.0.service_name', 'SEO')
                ->where('rows.0.service_color', '#22c55e')
            );
    }

    public function test_task_can_be_duplicated_with_people_and_subtasks(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $taskId = (string) Str::uuid();
        $subtaskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Task da duplicare',
            'priority' => 'urgent',
            'status' => 'done',
            'task_type' => 'project',
            'due_date' => '2026-06-30',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tasks')->insert([
            'id' => $subtaskId,
            'title' => 'Sottoattivita duplicata',
            'priority' => 'low',
            'status' => 'done',
            'task_type' => 'task',
            'parent_task_id' => $taskId,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('task_assignees')->insert([
            'id' => (string) Str::uuid(),
            'task_id' => $taskId,
            'user_id' => $assignee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post("/tasks/{$taskId}/duplicate")
            ->assertRedirect();

        $copy = DB::table('tasks')->where('title', 'Task da duplicare (copia)')->first();

        $this->assertNotNull($copy);
        $this->assertSame('todo', $copy->status);
        $this->assertSame('urgent', $copy->priority);

        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $copy->id,
            'user_id' => $assignee->id,
        ]);

        $this->assertDatabaseHas('tasks', [
            'parent_task_id' => $copy->id,
            'title' => 'Sottoattivita duplicata',
            'status' => 'todo',
        ]);
    }

    public function test_subtask_can_be_updated_inline_from_parent_detail(): void
    {
        $user = User::factory()->create();
        $parentId = (string) Str::uuid();
        $subtaskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $parentId,
            'title' => 'Task madre',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'project',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tasks')->insert([
            'id' => $subtaskId,
            'title' => 'Sottoattivita iniziale',
            'priority' => 'low',
            'status' => 'todo',
            'task_type' => 'task',
            'parent_task_id' => $parentId,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/tasks/{$subtaskId}", [
                'title' => 'Sottoattivita aggiornata',
                'task_type' => 'task',
                'status' => 'todo',
                'priority' => 'high',
                'due_date' => '2026-07-01',
                'recurring_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $subtaskId,
            'parent_task_id' => $parentId,
            'title' => 'Sottoattivita aggiornata',
            'priority' => 'high',
            'due_date' => '2026-07-01',
        ]);
    }

    public function test_task_comment_can_be_updated_and_deleted_inline(): void
    {
        $user = User::factory()->create();
        $taskId = (string) Str::uuid();
        $commentId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Task commenti',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'project',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('task_comments')->insert([
            'id' => $commentId,
            'task_id' => $taskId,
            'user_id' => $user->id,
            'content' => 'Commento iniziale',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/tasks/{$taskId}/comments/{$commentId}", [
                'content' => 'Commento aggiornato inline',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'id' => $commentId,
            'content' => 'Commento aggiornato inline',
        ]);
        $this->assertDatabaseHas('task_activity', [
            'task_id' => $taskId,
            'action' => 'comment_updated',
            'field' => 'content',
        ]);

        $this
            ->actingAs($user)
            ->delete("/tasks/{$taskId}/comments/{$commentId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('task_comments', [
            'id' => $commentId,
        ]);
        $this->assertDatabaseHas('task_activity', [
            'task_id' => $taskId,
            'action' => 'comment_deleted',
            'field' => 'content',
        ]);
    }

    public function test_task_schedule_can_be_updated_from_calendar(): void
    {
        $user = User::factory()->create();
        $taskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => 'Task calendario',
            'priority' => 'medium',
            'status' => 'todo',
            'task_type' => 'project',
            'start_date' => '2026-06-10',
            'due_date' => '2026-06-12',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->patch("/tasks/{$taskId}/schedule", [
                'start_date' => '2026-06-20',
                'due_date' => '2026-06-22',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'start_date' => '2026-06-20',
            'due_date' => '2026-06-22',
        ]);
    }

    public function test_task_dependencies_block_completion_until_dependency_is_done(): void
    {
        $user = User::factory()->create();
        $blockedTaskId = (string) Str::uuid();
        $dependencyTaskId = (string) Str::uuid();

        DB::table('tasks')->insert([
            [
                'id' => $blockedTaskId,
                'title' => 'Pubblicare campagna',
                'priority' => 'medium',
                'status' => 'todo',
                'task_type' => 'project',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $dependencyTaskId,
                'title' => 'Approvare visual',
                'priority' => 'medium',
                'status' => 'todo',
                'task_type' => 'project',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this
            ->actingAs($user)
            ->put("/tasks/{$blockedTaskId}/dependencies", [
                'dependency_ids' => [$dependencyTaskId],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $blockedTaskId,
            'depends_on_task_id' => $dependencyTaskId,
        ]);

        $this
            ->actingAs($user)
            ->patch("/tasks/{$blockedTaskId}/status", ['status' => 'done'])
            ->assertSessionHasErrors(['status']);

        $this->assertDatabaseHas('tasks', [
            'id' => $blockedTaskId,
            'status' => 'todo',
        ]);

        $this
            ->actingAs($user)
            ->patch("/tasks/{$dependencyTaskId}/status", ['status' => 'done'])
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->patch("/tasks/{$blockedTaskId}/status", ['status' => 'done'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $blockedTaskId,
            'status' => 'done',
        ]);
    }
}
