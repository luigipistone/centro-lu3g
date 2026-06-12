<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_header_can_be_updated_inline(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => 'Cliente fatturazione',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insert([
            'id' => $documentId,
            'client_id' => $clientId,
            'doc_type' => 'preventivo',
            'status' => 'draft',
            'issue_date' => '2026-06-12',
            'currency' => 'EUR',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/billing/{$documentId}/header", [
                'issue_date' => '2026-06-15',
                'due_date' => '2026-07-15',
                'status' => 'sent',
                'payment_method' => 'Bonifico',
                'payment_terms_days' => 30,
                'causale' => 'Sviluppo portale',
                'notes' => 'Nota interna',
                'footer_notes' => 'Grazie per la collaborazione',
                'withholding_pct' => 4,
                'pension_fund_pct' => 0,
                'pension_fund_label' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'issue_date' => '2026-06-15',
            'due_date' => '2026-07-15',
            'year' => 2026,
            'status' => 'sent',
            'payment_method' => 'Bonifico',
            'payment_terms_days' => 30,
            'causale' => 'Sviluppo portale',
            'notes' => 'Nota interna',
            'footer_notes' => 'Grazie per la collaborazione',
            'withholding_pct' => 4,
            'pension_fund_pct' => 0,
        ]);
    }
}
