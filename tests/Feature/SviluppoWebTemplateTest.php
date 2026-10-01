<?php

namespace Tests\Feature;

use App\Http\Controllers\CentroPageController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class SviluppoWebTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_creates_phases_tasks_subtasks_and_dependencies(): void
    {
        $actor = User::factory()->create();
        $template = DB::table('project_templates')->where('name', 'Modello Sviluppo Web')->firstOrFail();
        $sectionIds = DB::table('project_template_sections')->where('project_template_id', $template->id)->pluck('id');
        $this->assertCount(3, $sectionIds);
        $this->assertSame(9, DB::table('project_template_tasks')->whereIn('project_template_section_id', $sectionIds)->count());

        $projectId = (string) Str::uuid();
        DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Sito test',
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createTasks = new ReflectionMethod(CentroPageController::class, 'createProjectTasksFromTemplate');
        $createTasks->invoke(app(CentroPageController::class), $projectId, $template->id, '2026-10-01', $actor->id, null);

        $parents = DB::table('tasks')->where('project_id', $projectId)->whereNull('parent_task_id')->get();
        $this->assertCount(9, $parents);
        $this->assertSame(16, DB::table('tasks')->where('project_id', $projectId)->whereNotNull('parent_task_id')->count());

        $figma = $parents->firstWhere('title', 'Sviluppo mockup con Figma');
        $this->assertSame('2026-10-12', $figma->start_date);
        $this->assertSame('2026-10-17', $figma->due_date);
        $sviluppo = $parents->firstWhere('title', 'Sviluppo del sito web online');
        $this->assertSame(2, DB::table('task_dependencies')->where('task_id', $sviluppo->id)->count());
        $controllo = $parents->firstWhere('title', 'Controllo e Responsive');
        $this->assertSame(13, DB::table('tasks')->where('parent_task_id', $controllo->id)->count());
        $seo = DB::table('tasks')->where('parent_task_id', $controllo->id)->where('title', 'Impostare la SEO di base')->first();
        $this->assertSame('2026-11-24', $seo->start_date);
        $this->assertSame('2026-11-27', $seo->due_date);
    }
}
