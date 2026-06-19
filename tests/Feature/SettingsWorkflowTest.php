<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_settings_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->put('/settings/document', [
                'company_name' => 'Il Centro',
                'legal_form' => 'SRL',
                'vat_number' => '12345678901',
                'tax_code' => '12345678901',
                'tax_regime' => 'RF01',
                'street' => 'Via Roma',
                'street_number' => '10',
                'postal_code' => '20100',
                'city' => 'Milano',
                'province' => 'MI',
                'country' => 'IT',
                'email' => 'amministrazione@example.test',
                'pec' => 'pec@example.test',
                'phone' => '+3902000000',
                'sdi_code' => 'ABC1234',
                'iban' => 'IT60X0542811101000000123456',
                'bic_swift' => 'BCITITMM',
                'bank_name' => 'Banca Test',
                'default_payment_method' => 'Bonifico',
                'default_payment_terms_days' => 30,
                'default_withholding_pct' => 4,
                'default_pension_fund_label' => 'Cassa',
                'default_pension_fund_pct' => 4,
                'bollo_threshold' => 77.47,
                'bollo_amount' => 2,
                'bollo_charged_to_client' => true,
                'footer_notes' => 'Grazie.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Impostazioni aggiornate.');

        $this->assertDatabaseHas('document_settings', [
            'company_name' => 'Il Centro',
            'legal_form' => 'SRL',
            'city' => 'Milano',
            'default_payment_method' => 'Bonifico',
            'bollo_charged_to_client' => true,
            'footer_notes' => 'Grazie.',
        ]);

        $this->assertSame(1, DB::table('document_settings')->count());
    }
}
