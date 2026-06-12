<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_update_routes_match_services_case_insensitively(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente Social',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'Social',
            'color' => '#0ea5e9',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_services')->insert([
            'id' => (string) Str::uuid(),
            'client_id' => $clientId,
            'service_id' => $serviceId,
        ]);

        $this
            ->actingAs($user)
            ->get('/updates/social')
            ->assertOk();

        $this
            ->actingAs($user)
            ->post('/updates/social', [
                'client_id' => $clientId,
                'notes' => 'Nota social',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_service_updates', [
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'notes' => 'Nota social',
        ]);
    }

    public function test_service_update_rows_can_be_updated_inline(): void
    {
        $user = User::factory()->create();
        $responsible = User::factory()->create();
        $clientId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $updateId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente Newsletter',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'Newsletter',
            'color' => '#8b5cf6',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_services')->insert([
            'id' => (string) Str::uuid(),
            'client_id' => $clientId,
            'service_id' => $serviceId,
        ]);

        DB::table('client_service_updates')->insert([
            'id' => $updateId,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'notes' => 'Nota vecchia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/updates/newsletter/{$updateId}", [
                'client_id' => $clientId,
                'notes' => 'Nota aggiornata',
                'cadence' => 'weekly',
                'contact' => 'referente@example.test',
                'responsible_user_id' => $responsible->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_service_updates', [
            'id' => $updateId,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'notes' => 'Nota aggiornata',
            'cadence' => 'weekly',
            'contact' => 'referente@example.test',
            'responsible_user_id' => $responsible->id,
        ]);
    }

    public function test_attaching_service_to_client_makes_client_visible_in_updates_page(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente QA SEO',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'SEO',
            'color' => '#22c55e',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post("/clients/{$clientId}/services/{$serviceId}")
            ->assertRedirect();

        $this->assertDatabaseHas('client_services', [
            'client_id' => $clientId,
            'service_id' => $serviceId,
        ]);

        $this
            ->actingAs($user)
            ->get('/updates/seo')
            ->assertOk()
            ->assertSee('Cliente QA SEO');
    }

    public function test_client_service_can_be_detached_from_client_detail(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente QA ADV',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'ADV',
            'color' => '#f97316',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_services')->insert([
            'id' => (string) Str::uuid(),
            'client_id' => $clientId,
            'service_id' => $serviceId,
        ]);

        $this
            ->actingAs($user)
            ->delete("/clients/{$clientId}/services/{$serviceId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('client_services', [
            'client_id' => $clientId,
            'service_id' => $serviceId,
        ]);
    }
}
