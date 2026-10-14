<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.compress-pdf'))->assertForbidden();
        $this->actingAs($this->userWithRole('guest'))->get(route('tools.split-pdf'))->assertForbidden();
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
        $this->actingAs($manager)->get(route('tools.index'))->assertOk();
        $this->actingAs($manager)->get(route('tools.split-pdf'))->assertOk();
    }
}
