<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class ClientHierarchyTest extends TestCase
{
    use RefreshDatabase, ActsAsWorkflowAdmin;

    public function test_subclients_can_be_managed_and_selected_without_polluting_client_lists(): void
    {
        $user = User::factory()->create();
        $parent = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $parent, 'name' => 'Genitore', 'active' => true]);
        $this->actingAsWorkflowAdmin($user)->post("/clients/{$parent}/subclients", ['name' => 'Filiale'])
            ->assertSessionHasNoErrors();
        $child = DB::table('clients')->where('parent_client_id', $parent)->value('id');
        $this->assertNotNull($child);
        $plan = app(\App\Services\ReportAutomaticSync::class)->plan([['id' => 'remote', 'name' => 'Filiale']]);
        $this->assertNotContains($child, $plan['choices'] ?? []);
        $this->get('/clients')->assertInertia(fn (Assert $page) => $page->has('rows', 1));
        $this->get('/tasks')->assertInertia(fn (Assert $page) => $page->where('clients', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $child && $row['parent_client_id'] === $parent)));
        $this->put("/clients/{$parent}/subclients/{$child}", ['name' => 'Nuovo nome'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['id' => $child, 'name' => 'Nuovo nome']);
        $this->post("/clients/{$child}/subclients", ['name' => 'Livello vietato'])->assertNotFound();
        $this->post('/tasks', ['title' => 'Task filiale', 'client_id' => $child, 'task_type' => 'ongoing', 'status' => 'todo', 'priority' => 'medium'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tasks', ['title' => 'Task filiale', 'client_id' => $child]);
        $this->get("/clients/{$parent}")->assertInertia(fn (Assert $page) => $page->has('related.subclients', 1)->has('related.tasks', 1));
        $this->delete("/clients/{$parent}/subclients/{$child}")->assertSessionHasErrors('subclient');
        $this->assertDatabaseHas('clients', ['id' => $child]);
        DB::table('tasks')->where('client_id', $child)->delete();
        $this->delete("/clients/{$parent}/subclients/{$child}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('clients', ['id' => $child]);
    }

    public function test_subclient_management_checks_permissions_and_parent_ownership(): void
    {
        $user = User::factory()->create();
        $parent = (string) Str::uuid();
        $other = (string) Str::uuid();
        $child = (string) Str::uuid();
        DB::table('clients')->insert([['id' => $parent, 'name' => 'A'], ['id' => $other, 'name' => 'B']]);
        DB::table('clients')->insert(['id' => $child, 'name' => 'Child', 'parent_client_id' => $parent]);
        $this->actingAs($user)->post("/clients/{$parent}/subclients", ['name' => 'Denied'])->assertForbidden();
        $this->actingAsWorkflowAdmin($user)->put("/clients/{$other}/subclients/{$child}", ['name' => 'Wrong parent'])->assertNotFound();
        $this->delete("/clients/{$other}/subclients/{$child}")->assertNotFound();
        $this->post("/clients/{$parent}/subclients", ['name' => ''])->assertSessionHasErrors('name');
    }
}
