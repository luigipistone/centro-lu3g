<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_web_team_or_managers_can_open_repositories(): void
    {
        $web = $this->user('editor', 'web');
        $design = $this->user('editor', 'design');
        $manager = $this->user('admin', 'design');
        $superadmin = $this->user('superadmin', null);

        $this->actingAs($design)->get(route('tools.index'))
            ->assertInertia(fn (Assert $page) => $page->where('showWebRepositories', false));
        $this->actingAs($design)->get(route('tools.web-repositories'))->assertForbidden();

        foreach ([$web, $manager, $superadmin] as $user) {
            $this->actingAs($user)->get(route('tools.index'))
                ->assertInertia(fn (Assert $page) => $page->where('showWebRepositories', true));
            $this->actingAs($user)->get(route('tools.web-repositories'))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Centro/WebRepositories')->where('configured', false));
        }
    }

    public function test_superadmin_token_is_encrypted_and_repo_filter_is_applied(): void
    {
        Http::fake(['api.github.com/orgs/lu3gofficial/repos*' => Http::response([
            ['name' => 'web-plugin', 'custom_properties' => ['Plugin_Wordpress' => 'yes'], 'private' => true, 'description' => 'Plugin Web'],
            ['name' => 'other', 'custom_properties' => ['Plugin_Wordpress' => 'no'], 'private' => false],
        ])]);
        $superadmin = $this->user('superadmin', null);
        $manager = $this->user('admin', null);

        $this->actingAs($manager)->put(route('settings.github.update'), ['token' => 'github-secret'])->assertForbidden();
        $this->actingAs($superadmin)->put(route('settings.github.update'), ['token' => 'github-secret'])->assertSessionHasNoErrors();
        $encrypted = DB::table('github_settings')->value('encrypted_token');
        $this->assertNotSame('github-secret', $encrypted);
        $this->assertSame('github-secret', Crypt::decryptString($encrypted));

        $this->actingAs($manager)->get(route('tools.web-repositories'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('configured', true)
                ->has('repositories', 1)
                ->where('repositories.0.name', 'web-plugin')
                ->where('repositories.0.private', true));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer github-secret'));
    }

    private function user(string $role, ?string $division): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
        DB::table('profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'team_division' => $division, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
