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

    private function createDocument(User $user): array
    {
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

        return [$clientId, $documentId];
    }

    public function test_document_header_can_be_updated_inline(): void
    {
        $user = User::factory()->create();
        [, $documentId] = $this->createDocument($user);

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

    public function test_document_line_can_be_updated_inline_and_recalculates_totals(): void
    {
        $user = User::factory()->create();
        [, $documentId] = $this->createDocument($user);
        $lineId = (string) Str::uuid();

        DB::table('document_lines')->insert([
            'id' => $lineId,
            'document_id' => $documentId,
            'position' => 0,
            'description' => 'Riga iniziale',
            'quantity' => 1,
            'unit_price' => 50,
            'discount_pct' => 0,
            'vat_rate' => 22,
            'subtotal' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/billing/{$documentId}/lines/{$lineId}", [
                'description' => 'Consulenza aggiornata',
                'quantity' => 2,
                'unit_price' => 100,
                'discount_pct' => 10,
                'vat_rate' => 22,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_lines', [
            'id' => $lineId,
            'description' => 'Consulenza aggiornata',
            'quantity' => 2,
            'unit_price' => 100,
            'discount_pct' => 10,
            'vat_rate' => 22,
            'subtotal' => 180,
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'total_taxable' => 180,
            'total_vat' => 39.6,
            'total_amount' => 219.6,
        ]);
    }

    public function test_document_payment_can_be_updated_inline_and_recalculates_paid_amount(): void
    {
        $user = User::factory()->create();
        [, $documentId] = $this->createDocument($user);
        $lineId = (string) Str::uuid();
        $paymentId = (string) Str::uuid();

        DB::table('document_lines')->insert([
            'id' => $lineId,
            'document_id' => $documentId,
            'position' => 0,
            'description' => 'Setup',
            'quantity' => 1,
            'unit_price' => 100,
            'discount_pct' => 0,
            'vat_rate' => 22,
            'subtotal' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('document_payments')->insert([
            'id' => $paymentId,
            'document_id' => $documentId,
            'amount' => 10,
            'paid_at' => '2026-06-12',
            'method' => 'Bonifico',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->put("/billing/{$documentId}/payments/{$paymentId}", [
                'amount' => 60.5,
                'paid_at' => '2026-06-20',
                'method' => 'Carta',
                'notes' => 'Acconto',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_payments', [
            'id' => $paymentId,
            'amount' => 60.5,
            'paid_at' => '2026-06-20',
            'method' => 'Carta',
            'notes' => 'Acconto',
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'total_paid' => 60.5,
            'status' => 'partially_paid',
        ]);
    }
}
