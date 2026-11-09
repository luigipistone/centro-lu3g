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
            'https://www.googleapis.com/drive/v3/files/remote-file*' => fn ($request) => Http::response($request->method() === 'PATCH' ? ['id' => 'remote-file', 'trashed' => true] : ['id' => 'remote-file', 'driveId' => 'shared', 'capabilities' => ['canDownload' => true, 'canTrash' => true]]),
            'https://www.googleapis.com/drive/v3/files?*' => Http::response(['id' => 'new-folder', 'name' => 'Nuova cartella']),
        ]);
    }

    public function test_automatic_destination_uses_task_client_or_project_client_and_current_year(): void
    {
        $this->actingAsWorkflowAdmin(User::factory()->create());
        $this->travelTo(\Carbon\Carbon::parse('2026-12-31 23:30:00', 'UTC'));
        $client = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $client, 'name' => 'Cliente automatico']);
        $project = (string) Str::uuid();
        DB::table('projects')->insert(['id' => $project, 'name' => 'Project', 'status' => 'active', 'client_id' => $client]);
        $task = $this->task();
        DB::table('tasks')->where('id', $task)->update(['project_id' => $project]);
        $this->mock(GoogleDriveStorage::class, function ($mock) {
            $mock->shouldReceive('root')->andReturn('shared');
            $mock->shouldReceive('clientFolder')->with('Cliente automatico', '2027')->twice()->andReturn(['id' => 'destination', 'name' => 'CLIENTE AUTOMATICO']);
            $mock->shouldReceive('upload')->withArgs(fn ($file, $folder) => $folder === 'destination')->twice()->andReturn(['id' => 'file']);
        });
        foreach (['tasks' => $task, 'projects' => $project] as $scope => $id) {
            $this->postJson("/{$scope}/{$id}/drive-attachments", ['file' => UploadedFile::fake()->createWithContent('brief.txt', 'hello')])->assertCreated();
        }
        $this->assertDatabaseCount('drive_attachments', 2);
        $missing = $this->task();
        $this->postJson("/tasks/{$missing}/drive-attachments", ['file' => UploadedFile::fake()->createWithContent('brief.txt', 'hello')])->assertUnprocessable()->assertJsonValidationErrors('folder');
    }

    public function test_automatic_folders_search_all_pages_reuse_names_and_create_uppercase(): void
    {
        $drive = new class extends GoogleDriveStorage {
            public array $created = [];
            public function root(): string { return 'work'; }
            public function folders(string $parent, ?string $cursor): array {
                return match (true) {
                    $parent === 'work' => ['folders' => [['id' => 'year', 'name' => '2026']], 'cursor' => null],
                    $cursor === null => ['folders' => [['id' => 'other', 'name' => 'OTHER']], 'cursor' => 'next'],
                    default => ['folders' => [['id' => 'existing', 'name' => '  Acme  ']], 'cursor' => null],
                };
            }
            public function createFolder(string $parent, string $name): array {
                $this->created[] = [$parent, $name];
                return ['id' => 'new', 'name' => $name];
            }
        };
        $this->assertSame('existing', $drive->clientFolder('ACME', '2026')['id']);
        $this->assertSame([], $drive->created);
        $this->assertSame('NUOVO CLIENTE', $drive->clientFolder('Nuovo cliente', '2026')['name']);
        $this->assertSame([['year', 'NUOVO CLIENTE']], $drive->created);
    }

    public function test_duplicate_folder_names_block_automatic_destination(): void
    {
        $drive = new class extends GoogleDriveStorage {
            public function folders(string $parent, ?string $cursor): array {
                return ['folders' => [['id' => 'one', 'name' => 'ACME'], ['id' => 'two', 'name' => 'acme']], 'cursor' => null];
            }
        };
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $drive->findOrCreateFolder('year', 'Acme');
    }

    public function test_missing_year_and_client_folders_are_created_in_sequence(): void
    {
        $drive = new class extends GoogleDriveStorage {
            public array $created = [];
            public function root(): string { return 'work'; }
            public function folders(string $parent, ?string $cursor): array { return ['folders' => [], 'cursor' => null]; }
            public function createFolder(string $parent, string $name): array {
                $this->created[] = [$parent, $name];
                return ['id' => 'new-'.count($this->created), 'name' => $name];
            }
        };
        $this->assertSame('new-2', $drive->clientFolder('Acme', '2027')['id']);
        $this->assertSame([['work', '2027'], ['new-1', 'ACME']], $drive->created);
    }

    public function test_task_and_project_files_are_trashed_on_drive_before_unlinking(): void
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
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['trashed'] === true);
    }

    public function test_folder_creation_validates_the_parent_and_permissions(): void
    {
        $this->fakeDrive();
        $this->actingAsWorkflowAdmin(User::factory()->create());
        $id = $this->task();
        $this->postJson("/tasks/{$id}/drive-folders", ['parent' => 'folder', 'name' => 'Nuova cartella'])->assertCreated()->assertJsonPath('id', 'new-folder');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['mimeType'] === 'application/vnd.google-apps.folder' && $request['parents'] === ['folder']);
        $this->postJson("/tasks/{$id}/drive-folders", ['parent' => 'outside', 'name' => 'Non consentita'])->assertUnprocessable();
        $this->postJson("/tasks/{$id}/drive-folders", ['parent' => 'folder', 'name' => ' '])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson("/tasks/{$id}/drive-folders", ['parent' => 'folder', 'name' => 'Non consentita'])->assertForbidden();
    }

    public function test_failed_drive_deletion_keeps_the_attachment(): void
    {
        $this->fakeDrive();
        $this->actingAsWorkflowAdmin(User::factory()->create());
        $id = $this->task();
        $this->postJson("/tasks/{$id}/drive-attachments", ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'folder' => 'folder'])->assertCreated();
        $attachment = DB::table('drive_attachments')->value('id');
        $this->mock(GoogleDriveStorage::class, function ($mock) {
            $mock->shouldReceive('root')->andReturn('shared');
            $mock->shouldReceive('trash')->once()->andThrow(new \RuntimeException('Remote failure'));
        });
        $this->deleteJson("/tasks/{$id}/drive-attachments/{$attachment}")->assertUnprocessable();
        $this->assertDatabaseHas('drive_attachments', ['id' => $attachment]);
    }

    public function test_trash_checks_remote_permissions_and_accepts_already_trashed_files(): void
    {
        $this->fakeDrive();
        Http::fake([
            'https://www.googleapis.com/drive/v3/files/denied*' => Http::response(['id' => 'denied', 'driveId' => 'shared', 'capabilities' => ['canTrash' => false]]),
            'https://www.googleapis.com/drive/v3/files/trashed*' => Http::response(['id' => 'trashed', 'driveId' => 'shared', 'trashed' => true]),
        ]);
        app(GoogleDriveStorage::class)->trash('trashed');
        try {
            app(GoogleDriveStorage::class)->trash('denied');
            $this->fail('Deletion must require canTrash.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('drive', $error->errors());
        }
        Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
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
