<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ActsAsWorkflowAdmin;
use Tests\TestCase;

class SettingsWorkflowTest extends TestCase
{
    use ActsAsWorkflowAdmin;
    use RefreshDatabase;

    public function test_billing_tables_are_removed_without_removing_shared_features(): void
    {
        foreach ([
            'documents', 'document_lines', 'document_payments', 'document_activity',
            'document_emails', 'document_settings', 'document_numbering', 'subscriptions',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }

        foreach (['email_settings', 'company_documents', 'push_subscriptions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertFalse(Route::has('billing.index'));
        $this->assertFalse(Route::has('clients.subscriptions.store'));
    }

    public function test_clients_and_settings_remain_accessible_without_billing_tables(): void
    {
        $user = User::factory()->create();

        $this->actingAsWorkflowAdmin($user)->get('/clients')->assertOk();
        $this->actingAsWorkflowAdmin($user)->get('/settings')->assertOk();
    }
}
