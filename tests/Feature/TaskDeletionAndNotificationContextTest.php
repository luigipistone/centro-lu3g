<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CentroNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskDeletionAndNotificationContextTest extends TestCase
{
    use RefreshDatabase;

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
        foreach (['tasks.view', 'tasks.delete'] as $permission) {
            DB::table('role_permissions')->updateOrInsert(['role' => $role, 'permission' => $permission], ['allowed' => $role !== 'guest']);
        }
    }

    private function task(User $creator, string $type = 'ongoing'): string
    {
        $id = (string) Str::uuid();
        DB::table('tasks')->insert(['id' => $id, 'title' => 'Task riconoscibile', 'task_type' => $type, 'status' => 'todo', 'priority' => 'medium', 'created_by' => $creator->id]);
        return $id;
    }

    public function test_internal_roles_require_exact_confirmation_to_delete_their_tasks(): void
    {
        foreach (['editor', 'admin', 'superadmin'] as $role) {
            $user = User::factory()->create();
            $this->role($user, $role);
            $this->actingAs($user);
            foreach (['task', 'ongoing', 'meeting'] as $type) {
                $id = $this->task($user, $type);
                $this->delete("/tasks/{$id}")->assertSessionHasErrors('confirmation');
                $this->delete("/tasks/{$id}", ['confirmation' => 'elimina'])->assertSessionHasErrors('confirmation');
                $this->assertDatabaseHas('tasks', ['id' => $id]);
                $this->delete("/tasks/{$id}", ['confirmation' => 'ELIMINA'])->assertRedirect();
                $this->assertDatabaseMissing('tasks', ['id' => $id]);
            }
        }
    }

    public function test_editor_cannot_delete_others_tasks_and_guest_cannot_delete(): void
    {
        $owner = User::factory()->create();
        foreach (['editor', 'guest'] as $role) {
            $user = User::factory()->create();
            $this->role($user, $role);
            $id = $this->task($owner);
            $this->actingAs($user)->deleteJson("/tasks/{$id}", ['confirmation' => 'ELIMINA'])->assertForbidden();
            $this->assertDatabaseHas('tasks', ['id' => $id]);
        }
    }

    public function test_generic_task_notifications_include_title_without_duplicating_it(): void
    {
        $user = User::factory()->create();
        $id = $this->task($user);
        $service = app(CentroNotificationService::class);
        $service->notifyUsers([$user->id], $user->id, 'task_comment', 'Nuovo commento.', $id);
        $message = DB::table('notifications')->where('type', 'task_comment')->value('message');
        $this->assertStringContainsString('"Task riconoscibile"', $message);
        $service->notifyUsers([$user->id], $user->id, 'task_created', 'Task "Task riconoscibile" creata.', $id);
        $this->assertSame('Task "Task riconoscibile" creata.', DB::table('notifications')->where('type', 'task_created')->value('message'));
    }

    public function test_documents_and_messages_include_their_titles(): void
    {
        $user = User::factory()->create();
        $doc = (string) Str::uuid();
        $message = (string) Str::uuid();
        DB::table('company_documents')->insert(['id' => $doc, 'title' => 'Regolamento', 'file_path' => 'test.pdf', 'file_name' => 'test.pdf']);
        DB::table('company_messages')->insert(['id' => $message, 'title' => 'Riunione aziendale']);
        $service = app(CentroNotificationService::class);
        $service->notifyUsers([$user->id], null, 'company_document_updated', 'Categoria aggiornata.', null, $doc);
        $service->notifyUsers([$user->id], null, 'company_message_created', 'Nuovo messaggio.', null, null, $message);
        $this->assertStringContainsString('"Regolamento"', DB::table('notifications')->where('type', 'company_document_updated')->value('message'));
        $this->assertStringContainsString('"Riunione aziendale"', DB::table('notifications')->where('type', 'company_message_created')->value('message'));
    }

    public function test_password_events_include_only_title_and_only_authorized_recipients(): void
    {
        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $this->role($actor, 'superadmin');
        $vault = (string) Str::uuid();
        DB::table('password_vaults')->insert(['id' => $vault, 'name' => 'Team', 'visibility' => 'shared', 'created_by' => $actor->id]);
        DB::table('password_vault_user')->insert(['id' => (string) Str::uuid(), 'password_vault_id' => $vault, 'user_id' => $recipient->id]);
        $data = ['password_vault_id' => $vault, 'title' => 'Portale cliente', 'category' => 'other', 'credential_status' => 'active', 'username' => 'secret-user', 'password' => 'Secret-value-123!', 'notes' => 'Private note'];
        $this->actingAs($actor)->post(route('passwords.items.store'), $data)->assertSessionHasNoErrors();
        $id = DB::table('password_items')->value('id');
        $this->put(route('passwords.items.update', $id), $data + ['credential_status' => 'active'])->assertSessionHasNoErrors();
        $this->delete(route('passwords.items.destroy', $id))->assertSessionHasNoErrors();
        foreach (['password_created', 'password_updated', 'password_removed'] as $type) {
            $text = DB::table('notifications')->where('type', $type)->where('user_id', $recipient->id)->value('message');
            $this->assertStringContainsString('"Portale cliente"', $text);
            foreach (['secret-user', 'Secret-value-123!', 'Private note'] as $secret) $this->assertStringNotContainsString($secret, $text);
        }
        $this->assertDatabaseMissing('notifications', ['user_id' => $outsider->id]);
    }
}
