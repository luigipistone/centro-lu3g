<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_document_for_user_and_user_can_mark_it_read(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['name' => 'Admin']);
        $user = User::factory()->create(['name' => 'Mario Rossi']);
        $this->role($admin, 'admin');
        $this->role($user, 'editor');

        $this
            ->actingAs($admin)
            ->post(route('documents.store'), [
                'title' => 'Policy interna',
                'description' => 'Da leggere con attenzione.',
                'audience' => 'users',
                'user_ids' => [$user->id],
                'file' => UploadedFile::fake()->create('policy.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.index'))
            ->assertSessionHasNoErrors();

        $documentId = DB::table('company_documents')->value('id');
        $this->assertNotNull($documentId);
        $this->assertDatabaseHas('company_document_reads', [
            'company_document_id' => $documentId,
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'company_document_id' => $documentId,
            'type' => 'company_document_created',
            'read' => false,
        ]);

        $this
            ->actingAs($user)
            ->get(route('documents.show', $documentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centro/DocumentShow')
                ->where('document.id', $documentId)
                ->where('document.user_read_at', null)
            );

        $this
            ->actingAs($user)
            ->post(route('documents.read', $documentId))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(DB::table('company_document_reads')
            ->where('company_document_id', $documentId)
            ->where('user_id', $user->id)
            ->value('read_at'));
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
