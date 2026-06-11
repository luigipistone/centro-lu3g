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
}
