<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_contact_can_be_updated_inline(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $contactId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente contatti',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_contacts')->insert([
            'id' => $contactId,
            'client_id' => $clientId,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario@example.test',
            'phone' => '123',
            'role' => 'Marketing',
            'notes' => 'Nota iniziale',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/clients/{$clientId}/contacts/{$contactId}", [
                'first_name' => 'Luisa',
                'last_name' => 'Verdi',
                'email' => 'luisa@example.test',
                'phone' => '',
                'role' => 'Direzione',
                'notes' => 'Referente principale',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_contacts', [
            'id' => $contactId,
            'client_id' => $clientId,
            'first_name' => 'Luisa',
            'last_name' => 'Verdi',
            'email' => 'luisa@example.test',
            'phone' => null,
            'role' => 'Direzione',
            'notes' => 'Referente principale',
        ]);
    }
}
