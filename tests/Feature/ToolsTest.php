<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Jobs\ConvertVideoToWebm;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role,
        ]);
        return $user;
    }

    public function test_tools_requires_login_and_role_permission(): void
    {
        $this->get(route('tools.index'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('editor'))->get(route('tools.index'))->assertOk();
        $this->actingAs($this->userWithRole('editor'))->get(route('tools.compress-pdf'))->assertOk();
        $this->actingAs($this->userWithRole('editor'))->get(route('tools.split-pdf'))->assertOk();
        $this->actingAs($this->userWithRole('editor'))->get(route('tools.images-to-pdf'))->assertOk();
        $this->actingAs($this->userWithRole('editor'))->get(route('tools.extract-images'))->assertOk();
        $this->actingAs($this->userWithRole('editor'))->get(route('tools.video-to-webm'))->assertOk();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.compress-pdf'))->assertForbidden();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.split-pdf'))->assertForbidden();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.images-to-pdf'))->assertForbidden();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.extract-images'))->assertForbidden();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.video-to-webm'))->assertForbidden();
    }

    public function test_disabled_tools_section_blocks_employee_but_not_manager(): void
    {
        $employee = $this->userWithRole('editor');
        $manager = $this->userWithRole('admin');
        DB::table('section_availability')->insert([
            'section_key' => 'tools', 'enabled' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($employee)->get(route('tools.index'))->assertStatus(503);
        $this->actingAs($employee)->get(route('tools.compress-pdf'))->assertStatus(503);
        $this->actingAs($employee)->get(route('tools.split-pdf'))->assertStatus(503);
        $this->actingAs($employee)->get(route('tools.images-to-pdf'))->assertStatus(503);
        $this->actingAs($employee)->get(route('tools.extract-images'))->assertStatus(503);
        $this->actingAs($employee)->get(route('tools.video-to-webm'))->assertStatus(503);
        $this->actingAs($manager)->get(route('tools.index'))->assertOk();
        $this->actingAs($manager)->get(route('tools.split-pdf'))->assertOk();
        $this->actingAs($manager)->get(route('tools.images-to-pdf'))->assertOk();
        $this->actingAs($manager)->get(route('tools.extract-images'))->assertOk();
        $this->actingAs($manager)->get(route('tools.video-to-webm'))->assertOk();
    }

    public function test_video_upload_is_private_and_queued(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('video.ffmpeg', PHP_BINARY);
        config()->set('video.ffprobe', PHP_BINARY);
        $owner = $this->userWithRole('editor');
        $other = $this->userWithRole('editor');

        $response = $this->actingAs($owner)->postJson(route('tools.video-to-webm.store'), [
            'video' => UploadedFile::fake()->create('clip.mov', 1024, 'video/quicktime'),
            'quality' => 'balanced',
        ])->assertAccepted()->assertJsonStructure(['id']);
        $id = $response->json('id');
        Storage::disk('local')->assertExists('video-conversions/'.$id.'/source.mov');
        Queue::assertPushed(ConvertVideoToWebm::class, 1);
        $this->actingAs($owner)->getJson(route('tools.video-to-webm.status', $id))->assertOk()->assertJsonPath('status', 'queued');
        $this->actingAs($other)->getJson(route('tools.video-to-webm.status', $id))->assertNotFound();
        $this->actingAs($other)->get(route('tools.video-to-webm.download', $id))->assertNotFound();
    }

    public function test_video_upload_rejects_wrong_format_and_unavailable_converter(): void
    {
        Storage::fake('local');
        config()->set('video.ffmpeg', PHP_BINARY);
        config()->set('video.ffprobe', PHP_BINARY);
        $user = $this->userWithRole('editor');
        $this->actingAs($user)->postJson(route('tools.video-to-webm.store'), [
            'video' => UploadedFile::fake()->create('clip.avi', 1024, 'video/x-msvideo'),
            'quality' => 'balanced',
        ])->assertStatus(422);

        config()->set('video.ffmpeg', '/missing/ffmpeg');
        $this->actingAs($user)->postJson(route('tools.video-to-webm.store'), [
            'video' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
            'quality' => 'high',
        ])->assertStatus(503);
        $this->assertDatabaseCount('video_conversions', 0);
    }

    public function test_expired_video_files_are_removed(): void
    {
        Storage::fake('local');
        $user = $this->userWithRole('editor');
        $id = (string) Str::uuid();
        DB::table('video_conversions')->insert([
            'id' => $id, 'user_id' => $user->id, 'original_name' => 'clip.mov',
            'quality' => 'balanced', 'status' => 'done', 'expires_at' => now()->subMinute(),
            'created_at' => now()->subHours(2), 'updated_at' => now(),
        ]);
        Storage::disk('local')->put('video-conversions/'.$id.'/output.webm', 'test');
        $this->actingAs($user)->get(route('tools.video-to-webm.download', $id))->assertNotFound();
        $this->artisan('centro:clean-video-conversions')->assertSuccessful();
        Storage::disk('local')->assertMissing('video-conversions/'.$id.'/output.webm');
        $this->assertDatabaseMissing('video_conversions', ['id' => $id]);
    }
}
