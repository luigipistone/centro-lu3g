<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class ClientArchiveTest extends TestCase
{
    use ActsAsWorkflowAdmin;
    use RefreshDatabase;

    public function test_client_moves_between_active_list_and_archive_without_losing_data(): void
    {
        $admin = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('clients')->insert([
            'id' => $id,
            'name' => 'Cliente esempio',
            'notes' => 'Da conservare',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsWorkflowAdmin($admin)->get(route('clients.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Centro/Index')->where('clientArchive', false)->has('rows', 1));
        $this->actingAsWorkflowAdmin($admin)->patch(route('clients.active', $id), ['active' => false])->assertRedirect();
        $this->actingAsWorkflowAdmin($admin)->get(route('clients.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Centro/Index')->where('clientArchive', false)->has('rows', 0));
        $this->actingAsWorkflowAdmin($admin)->get(route('clients.archive'))
            ->assertInertia(fn (Assert $page) => $page->component('Centro/Index')->where('clientArchive', true)->has('rows', 1));
        $this->assertDatabaseHas('clients', ['id' => $id, 'active' => false, 'notes' => 'Da conservare']);

        $this->actingAsWorkflowAdmin($admin)->patch(route('clients.active', $id), ['active' => true])->assertRedirect();
        $this->actingAsWorkflowAdmin($admin)->get(route('clients.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Centro/Index')->has('rows', 1));
    }
}
