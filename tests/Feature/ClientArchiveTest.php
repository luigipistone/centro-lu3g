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

    public function test_default_order_is_alphabetical_and_removed_fields_are_not_available(): void
    {
        $admin = User::factory()->create();
        foreach ([true, false] as $active) {
            foreach (['Zeta', 'Alfa'] as $name) DB::table('clients')->insert(['id' => (string) Str::uuid(), 'name' => $name, 'active' => $active]);
        }
        foreach (['clients.index', 'clients.archive'] as $route) {
            $this->actingAsWorkflowAdmin($admin)->getJson(route($route, ['client_page' => 1]))
                ->assertOk()->assertJsonPath('rows.0.name', 'Alfa')->assertJsonPath('rows.1.name', 'Zeta');
        }
        foreach (['legal_form', 'source', 'iban', 'sdi_code', 'pec', 'electronic_invoicing_address'] as $field) {
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('clients', $field));
        }
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('clients', 'vat_number'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('clients', 'tax_code'));
    }

    public function test_scrolling_and_search_cover_all_active_and_archived_clients(): void
    {
        $admin = User::factory()->create();
        foreach ([true, false] as $active) {
            for ($i = 0; $i < 125; $i++) {
                DB::table('clients')->insert(['id' => (string) Str::uuid(), 'name' => sprintf('Client %03d', $i),
                    'legal_name' => $i === 124 ? 'Unique Legal Company' : null, 'active' => $active]);
            }
        }
        foreach (['clients.index', 'clients.archive'] as $route) {
            $ids = [];
            for ($page = 1; $page <= 6; $page++) {
                $response = $this->actingAsWorkflowAdmin($admin)->getJson(route($route, ['client_page' => 1, 'page' => $page, 'sort' => 'name_asc']))->assertOk();
                $ids = array_merge($ids, array_column($response->json('rows'), 'id'));
                $response->assertJsonPath('pagination.total', 125);
            }
            $this->assertCount(125, array_unique($ids));
            $this->getJson(route($route, ['client_page' => 1, 'search' => 'unique legal']))
                ->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.name', 'Client 124');
        }
    }

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
