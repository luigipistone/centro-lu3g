<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_follow_a_short_link(): void
    {
        $user = User::factory()->create();
        $this->allowTools($user);
        $this->actingAs($user)->postJson(route('tools.short-links.store'), [
            'url' => 'https://example.com/some/long/path?foo=bar',
        ])->assertCreated();

        $link = DB::table('short_links')->first();
        $this->assertSame(6, strlen($link->code));
        $this->get(route('short-links.redirect', $link->code))
            ->assertRedirect('https://example.com/some/long/path?foo=bar');
        $this->assertDatabaseHas('short_links', ['id' => $link->id, 'clicks' => 1]);
    }

    public function test_displayed_url_uses_public_host_without_public_directory(): void
    {
        config(['app.url' => 'https://centro.lu3g.com/public']);
        config(['short-links.base_url' => 'https://lu3g.com']);
        $user = User::factory()->create();
        $this->allowTools($user);
        $response = $this->actingAs($user)
            ->postJson('https://centro.lu3g.com/tools/short-links', ['url' => 'https://example.com/long/path']);

        $response->assertCreated();
        $this->assertMatchesRegularExpression('~^https://lu3g\.com/s/[A-Za-z0-9]{6}$~', $response->json('link.short_url'));
    }

    public function test_existing_eight_character_link_keeps_working(): void
    {
        $owner = User::factory()->create();
        DB::table('short_links')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id, 'code' => 'Abcd1234',
            'destination_url' => 'https://example.com', 'clicks' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/s/Abcd1234')->assertRedirect('https://example.com');
        $this->get('/go/Abcd1234')->assertRedirect('https://example.com');
        $this->get('/public/go/Abcd1234')->assertRedirect('https://example.com');
    }

    public function test_private_or_non_web_destinations_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->allowTools($user);
        foreach (['javascript:alert(1)', 'http://127.0.0.1/admin', 'https://localhost/test', 'https://user:pass@example.com'] as $url) {
            $this->actingAs($user)->postJson(route('tools.short-links.store'), ['url' => $url])->assertUnprocessable();
        }
        $this->assertDatabaseCount('short_links', 0);
    }

    public function test_users_only_see_and_delete_their_own_links(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->allowTools($owner);
        $this->allowTools($other);
        $id = (string) Str::uuid();
        DB::table('short_links')->insert([
            'id' => $id, 'user_id' => $owner->id, 'code' => 'Abcd1234',
            'destination_url' => 'https://example.com', 'clicks' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($other)->get(route('tools.short-links'))->assertInertia(fn ($page) => $page->has('links', 0));
        $this->actingAs($other)->delete(route('tools.short-links.destroy', $id))->assertForbidden();
        $this->actingAs($owner)->delete(route('tools.short-links.destroy', $id))->assertNoContent();
    }

    public function test_click_counts_update_without_exposing_other_users_links(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->allowTools($owner);
        $this->allowTools($other);
        $this->actingAs($owner)->postJson(route('tools.short-links.store'), ['url' => 'https://example.com'])->assertCreated();
        $link = DB::table('short_links')->first();

        $this->actingAs($other)->getJson(route('tools.short-links.counts'))->assertOk()->assertJsonCount(0, 'counts');
        $this->actingAs($owner)->get(route('short-links.redirect', $link->code))->assertRedirect('https://example.com');
        $this->actingAs($owner)->getJson(route('tools.short-links.counts'))
            ->assertOk()
            ->assertJsonPath('counts.0.id', $link->id)
            ->assertJsonPath('counts.0.clicks', 1);
    }

    public function test_superadmin_can_manage_links_created_by_others(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'role' => 'superadmin']);
        $id = (string) Str::uuid();
        DB::table('short_links')->insert([
            'id' => $id, 'user_id' => $owner->id, 'code' => 'ZxYw4321',
            'destination_url' => 'https://example.com', 'clicks' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('tools.short-links'))->assertInertia(fn ($page) => $page->has('links', 1));
        $this->actingAs($admin)->delete(route('tools.short-links.destroy', $id))->assertNoContent();
    }

    private function allowTools(User $user): void
    {
        DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'editor']);
        DB::table('role_permissions')->where('role', 'editor')->where('permission', 'tools.view')->update(['allowed' => true]);
    }
}
