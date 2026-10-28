<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AirmailStorage;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AirmailTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
        return $user;
    }

    public function test_settings_are_superadmin_only_and_password_is_encrypted(): void
    {
        $editor = $this->userWithRole('editor');
        $admin = $this->userWithRole('admin');
        $superadmin = $this->userWithRole('superadmin');

        $this->actingAs($editor)->put(route('settings.airmail.update'), ['password' => 'temporary-test'])->assertForbidden();
        $this->actingAs($admin)->put(route('settings.airmail.update'), ['password' => 'temporary-test'])->assertForbidden();
        $this->actingAs($superadmin)->put(route('settings.airmail.update'), ['password' => 'temporary-test'])->assertRedirect();
        $stored = DB::table('airmail_settings')->value('encrypted_password');
        $this->assertNotSame('temporary-test', $stored);
        $this->assertSame('temporary-test', Crypt::decryptString($stored));
    }

    public function test_upload_is_owned_and_public_link_expires(): void
    {
        $owner = $this->userWithRole('editor');
        $other = $this->userWithRole('editor');
        $storage = Mockery::mock(AirmailStorage::class);
        $storage->shouldReceive('isConfigured')->andReturn(true);
        $storage->shouldReceive('createTransferFolder')->once();
        $storage->shouldReceive('uploadChunk')->once();
        $storage->shouldReceive('finishFile')->once();
        $this->app->instance(AirmailStorage::class, $storage);

        $created = $this->actingAs($owner)->postJson(route('tools.airmail.create'), [
            'recipient_email' => 'recipient@example.com', 'expires_days' => 1,
            'files' => [['name' => 'report.pdf', 'size' => 1024]],
        ])->assertCreated()->json();
        $id = $created['id'];
        $fileId = $created['files'][0]['id'];
        $this->actingAs($other)->getJson(route('tools.airmail.show', $id))->assertNotFound();
        $this->actingAs($other)->postJson(route('tools.airmail.chunk', [$id, $fileId]), [
            'index' => 1, 'chunk' => UploadedFile::fake()->create('part.bin', 1),
        ])->assertNotFound();
        $this->actingAs($owner)->postJson(route('tools.airmail.publish', $id))->assertStatus(409);
        $this->actingAs($owner)->postJson(route('tools.airmail.chunk', [$id, $fileId]), [
            'index' => 1, 'chunk' => UploadedFile::fake()->create('part.bin', 1),
        ])->assertOk()->assertJsonPath('complete', true);
        $published = $this->actingAs($owner)->postJson(route('tools.airmail.publish', $id))->assertOk()->json();
        $this->assertFalse($published['email_sent']);
        $path = parse_url($published['transfer']['url'], PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('report.pdf');
        auth()->logout();
        $this->get($path)->assertOk()->assertSee('report.pdf');
        $this->get(route('airmail.download', [basename($path), (string) Str::uuid()]))->assertNotFound();
        $storage->shouldReceive('openFile')->once()->andReturn(new ClientResponse(new PsrResponse(206, ['Content-Range' => 'bytes 100-199/1024'], str_repeat('a', 100))));
        $this->withHeader('Range', 'bytes=100-199')->get(route('airmail.download', [basename($path), $fileId]))
            ->assertStatus(206)->assertHeader('Content-Length', '100')->assertHeader('Content-Range', 'bytes 100-199/1024');
        DB::table('airmail_transfers')->where('id', $id)->update(['expires_at' => now()->subMinute()]);
        $this->get($path)->assertNotFound();
    }

    public function test_total_size_limit_and_missing_configuration(): void
    {
        $user = $this->userWithRole('editor');
        $this->actingAs($user)->postJson(route('tools.airmail.create'), [
            'recipient_email' => 'recipient@example.com', 'expires_days' => 1,
            'files' => [['name' => 'f.txt', 'size' => 1]],
        ])->assertStatus(503);

        $storage = Mockery::mock(AirmailStorage::class);
        $storage->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(AirmailStorage::class, $storage);
        $this->actingAs($user)->postJson(route('tools.airmail.create'), [
            'recipient_email' => 'recipient@example.com', 'expires_days' => 1,
            'files' => [
                ['name' => 'one.bin', 'size' => 2 * 1024 * 1024 * 1024],
                ['name' => 'two.bin', 'size' => 2 * 1024 * 1024 * 1024],
            ],
        ])->assertUnprocessable();
    }
}
