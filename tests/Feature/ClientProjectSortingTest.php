<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class ClientProjectSortingTest extends TestCase
{
    use ActsAsWorkflowAdmin;
    use RefreshDatabase;

    public function test_clients_and_projects_sort_by_name_and_projects_by_creation_date(): void
    {
        $admin = User::factory()->create();
        foreach ([['Zeta', '2026-01-01'], ['Alfa', '2026-02-01']] as [$name, $createdAt]) {
            DB::table('clients')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'active' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            DB::table('projects')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        foreach ([
            ['clients.index', 'name_asc', 'Alfa'],
            ['clients.index', 'name_desc', 'Zeta'],
            ['projects.index', 'name_asc', 'Alfa'],
            ['projects.index', 'name_desc', 'Zeta'],
            ['projects.index', 'created_asc', 'Zeta'],
            ['projects.index', 'created_desc', 'Alfa'],
        ] as [$routeName, $sort, $firstName]) {
            $this->actingAsWorkflowAdmin($admin)->get(route($routeName, ['sort' => $sort]))
                ->assertInertia(fn (Assert $page) => $page->component('Centro/Index')->where('rows.0.name', $firstName));
        }
    }
}
