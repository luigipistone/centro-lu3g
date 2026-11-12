<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TextSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class TextSearchTest extends TestCase
{
    use ActsAsWorkflowAdmin, RefreshDatabase;

    public function test_search_normalizes_names_and_punctuation_before_pagination(): void
    {
        $admin = User::factory()->create();
        for ($i = 0; $i < 30; $i++) $this->client('Alfa '.$i);
        $this->client('Zeta Nicolò D’Angelo S.r.l.');
        $this->client('Zeta Nicolò D’Angelo S.r.l.', false);

        foreach (['clients.index', 'clients.archive'] as $route) {
            $this->actingAsWorkflowAdmin($admin)->get(route($route, ['search' => 'srl dangelo nicolo']))
                ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.name', 'Zeta Nicolò D’Angelo S.r.l.'));
        }
    }

    public function test_all_search_words_are_required_and_filters_remain_in_effect(): void
    {
        $id = $this->client('ONE-TEAM', true, 'info@example.it');
        $this->client('ONE-TEAM', false, 'info@example.it');
        $query = DB::table('clients')->where('active', true);
        TextSearch::apply($query, ['name', 'email'], 'example oneteam');
        $this->assertSame([$id], $query->pluck('id')->all());
        TextSearch::apply($query, ['name', 'email'], 'missing');
        $this->assertSame(0, $query->count());
    }

    private function client(string $name, bool $active = true, ?string $email = null): string
    {
        $id = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $id, 'name' => $name, 'active' => $active, 'email' => $email, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }
}
