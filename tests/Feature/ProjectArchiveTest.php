<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class ProjectArchiveTest extends TestCase
{
    use ActsAsWorkflowAdmin;
    use RefreshDatabase;

    public function test_archived_projects_are_separate_and_sortable_without_deleting_data(): void
    {
        $admin = User::factory()->create();
        $archived = null;
        foreach (['active', 'completed', 'on_hold', 'archived'] as $status) {
            $id = (string) Str::uuid();
            DB::table('projects')->insert(['id' => $id, 'name' => $status, 'status' => $status, 'description' => 'Conservare', 'created_at' => now(), 'updated_at' => now()]);
            if ($status === 'archived') $archived = $id;
        }
        $this->actingAsWorkflowAdmin($admin)->get(route('projects.index', ['sort' => 'name_asc']))
            ->assertInertia(fn (Assert $page) => $page->component('Centro/Index')->where('projectArchive', false)->has('rows', 3)->where('rows.0.status', 'active')->where('rows.1.status', 'completed')->where('rows.2.status', 'on_hold'));
        foreach (['name_asc', 'created_desc'] as $sort) {
            $this->get(route('projects.archive', ['sort' => $sort]))
                ->assertInertia(fn (Assert $page) => $page->component('Centro/Index')->where('projectArchive', true)->has('rows', 1)->where('rows.0.id', $archived));
        }
        DB::table('projects')->where('id', $archived)->update(['status' => 'active']);
        $this->get(route('projects.archive'))->assertInertia(fn (Assert $page) => $page->has('rows', 0));
        $this->get(route('projects.index'))->assertInertia(fn (Assert $page) => $page->has('rows', 4));
        $this->assertDatabaseHas('projects', ['id' => $archived, 'description' => 'Conservare']);
    }

    public function test_archive_requires_authentication(): void
    {
        $this->get(route('projects.archive'))->assertRedirect(route('login'));
    }
}
