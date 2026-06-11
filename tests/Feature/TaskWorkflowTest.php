<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
}
