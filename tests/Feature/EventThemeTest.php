<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EventThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_starts_standard_and_can_be_changed_and_restored_globally(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $employee = $this->userWithRole('editor');

        $this->assertSame('standard', app(EventThemeService::class)->current());
        $this->actingAs($superadmin)->put(route('settings.event-theme.update'), ['theme' => 'natale'])
            ->assertRedirect(route('settings.index', ['tab' => 'personalizzazione']));

        $this->assertSame('natale', app(EventThemeService::class)->current());
        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-event-theme="natale"', false)
            ->assertInertia(fn ($page) => $page->where('eventTheme', 'natale'));

        $this->actingAs($superadmin)->put(route('settings.event-theme.update'), ['theme' => 'standard'])
            ->assertRedirect();
        $this->assertSame('standard', app(EventThemeService::class)->current());
        $this->assertDatabaseCount('app_settings', 1);
    }

    public function test_only_superadmin_can_change_theme_and_invalid_values_are_rejected(): void
    {
        $manager = $this->userWithRole('admin');
        $superadmin = $this->userWithRole('superadmin');

        $this->actingAs($manager)->put(route('settings.event-theme.update'), ['theme' => 'estate'])->assertForbidden();
        $this->actingAs($superadmin)->put(route('settings.event-theme.update'), ['theme' => 'unsupported'])
            ->assertSessionHasErrors('theme');
        $this->assertSame('standard', app(EventThemeService::class)->current());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
