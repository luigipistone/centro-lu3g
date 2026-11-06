<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleDriveStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class DriveAttachmentsTest extends TestCase
{
    use RefreshDatabase, ActsAsWorkflowAdmin;

    private function task(): string
    {
        $id = (string) Str::uuid();
        DB::table('tasks')->insert(['id' => $id, 'title' => 'Drive task', 'task_type' => 'ongoing', 'status' => 'todo', 'priority' => 'medium']);

        return $id;
    }

    private function fakeDrive(): void
    {
        config(['google-drive.shared_drive_id' => 'shared']);
        $this->app->instance(GoogleDriveStorage::class, new class extends GoogleDriveStorage {
            public function configured(): bool { return true; }
            protected function token(): string { return 'test'; }
        });
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/drive/v3/files/folder*' => Http::response(['id' => 'folder', 'name' => 'Clienti', 'driveId' => 'shared', 'mimeType' => 'application/vnd.google-apps.folder', 'capabilities' => ['canAddChildren' => true]]),
            'https://www.googleapis.com/drive/v3/files/outside*' => Http::response(['id' => 'outside', 'driveId' => 'other', 'mimeType' => 'application/vnd.google-apps.folder', 'capabilities' => ['canAddChildren' => true]]),
            'https://www.googleapis.com/upload/*' => Http::response([], 200, ['Location' => 'https://www.googleapis.com/session/test']),
            'https://www.googleapis.com/session/test' => Http::response(['id' => 'remote-file', 'size' => '5']),
            'https://www.googleapis.com/drive/v3/files/remote-file*' => Http::response(['id' => 'remote-file', 'driveId' => 'shared', 'capabilities' => ['canDownload' => true]]),
        ]);
    }

    public function test_task_and_project_uploads_are_stored_on_drive_and_unlinked_only_locally(): void
    {
        $this->fakeDrive();
        $this->actingAsWorkflowAdmin(User::factory()->create());
        $task = $this->task();
        $project = (string) Str::uuid();
        DB::table('projects')->insert(['id' => $project, 'name' => 'Drive project', 'status' => 'active']);
        foreach (['tasks' => $task, 'projects' => $project] as $scope => $id) {
            $this->postJson("/{$scope}/{$id}/drive-attachments", ['file' => UploadedFile::fake()->createWithContent('brief.txt', 'hello'), 'folder' => 'folder'])->assertCreated();
            $list = $this->getJson("/{$scope}/{$id}/drive-attachments")->assertOk()->assertJsonCount(1, 'files')->assertJsonPath('files.0.name', 'brief.txt');
            $attachment = $list->json('files.0.id');
            $this->deleteJson("/{$scope}/{$id}/drive-attachments/{$attachment}")->assertOk();
            $this->assertDatabaseMissing('drive_attachments', ['id' => $attachment]);
        }
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_external_folders_and_upload_failures_do_not_create_records(): void
    {
        $this->fakeDrive();
        $this->actingAsWorkflowAdmin(User::factory()->create());
        $id = $this->task();
        $this->postJson("/tasks/{$id}/drive-attachments", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'folder' => 'outside'])->assertUnprocessable();
        $this->mock(GoogleDriveStorage::class, function ($mock) {
            $mock->shouldReceive('folder')->andReturn(['id' => 'folder', 'name' => 'Clienti']);
            $mock->shouldReceive('upload')->andThrow(new \RuntimeException('Upload failed'));
        });
        $this->postJson("/tasks/{$id}/drive-attachments", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'folder' => 'folder'])->assertUnprocessable();
        $this->assertDatabaseCount('drive_attachments', 0);
    }

    public function test_guests_cannot_browse_the_shared_drive_and_attachments_are_scoped(): void
    {
        $this->fakeDrive();
        $guest = User::factory()->create();
        $id = $this->task();
        $this->actingAs($guest)->getJson("/tasks/{$id}/drive-attachments")->assertForbidden();
        DB::table('task_followers')->insert(['id' => (string) Str::uuid(), 'task_id' => $id, 'user_id' => $guest->id]);
        $this->getJson("/tasks/{$id}/drive-attachments")->assertOk()->assertJsonPath('can_upload', false);
        $this->getJson("/tasks/{$id}/drive-folders")->assertForbidden();
        $this->actingAsWorkflowAdmin(User::factory()->create());
        $this->postJson("/tasks/{$id}/drive-attachments", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'folder' => 'folder'])->assertCreated();
        $attachment = DB::table('drive_attachments')->value('id');
        $other = $this->task();
        $this->getJson("/tasks/{$other}/drive-attachments/{$attachment}")->assertNotFound();
        $this->deleteJson("/tasks/{$other}/drive-attachments/{$attachment}")->assertNotFound();
        $this->assertDatabaseCount('drive_attachments', 1);
    }
}
