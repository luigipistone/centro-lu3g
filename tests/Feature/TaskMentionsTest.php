<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class TaskMentionsTest extends TestCase
{
    use RefreshDatabase, ActsAsWorkflowAdmin;

    public function test_mentions_notify_participants_once_and_ignore_unauthorized_ids(): void
    {
        [$author, $mentioned, $follower, $outsider] = User::factory()->count(4)->create()->all();
        $task = (string) Str::uuid();
        DB::table('tasks')->insert(['id' => $task, 'title' => 'Mention test', 'task_type' => 'ongoing', 'status' => 'todo', 'priority' => 'medium']);
        foreach ([$author, $mentioned, $follower] as $user) {
            DB::table('task_followers')->insert(['id' => (string) Str::uuid(), 'task_id' => $task, 'user_id' => $user->id]);
        }
        $this->actingAsWorkflowAdmin($author)->getJson("/tasks/{$task}/mention-users")
            ->assertOk()->assertJsonCount(2)->assertJsonMissing(['id' => $outsider->id]);
        $content = '<span data-mention-user="'.$mentioned->id.'">@User</span> <span data-mention-user="'.$mentioned->id.'">@User</span>'
            .'<span data-mention-user="'.$outsider->id.'">@Outside</span>';
        $this->post("/tasks/{$task}/comments", ['content' => $content])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notifications', ['user_id' => $mentioned->id, 'task_id' => $task, 'type' => 'task_mention']);
        $this->assertDatabaseHas('notifications', ['user_id' => $follower->id, 'task_id' => $task, 'type' => 'task_comment']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $mentioned->id, 'type' => 'task_comment']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $outsider->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $author->id]);
        $this->assertSame(1, DB::table('notifications')->where('type', 'task_mention')->count());
        $comment = DB::table('task_comments')->where('task_id', $task)->value('id');
        DB::table('notifications')->delete();
        $this->put("/tasks/{$task}/comments/{$comment}", ['content' => $content.' modified'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('notifications', ['type' => 'task_mention']);
        $this->put("/tasks/{$task}/comments/{$comment}", ['content' => $content.'<span data-mention-user="'.$follower->id.'">@New</span>'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notifications', ['type' => 'task_mention', 'user_id' => $follower->id]);
    }
}
