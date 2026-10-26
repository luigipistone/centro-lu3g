<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CentroNotificationService;
use App\Services\NotificationDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_link_to_their_relevant_sections(): void
    {
        $this->assertSame(route('tasks.show', 'task-id'), NotificationDestination::url('task_updated', 'task-id'));
        $this->assertSame(route('tasks.index'), NotificationDestination::url('task_deleted', 'task-id'));
        $this->assertSame(route('projects.index'), NotificationDestination::url('project_updated'));
        $this->assertSame(route('absences.index'), NotificationDestination::url('absence_created', role: 'superadmin'));
        $this->assertSame(route('profile.edit'), NotificationDestination::url('absence_created', role: 'editor'));
        $this->assertSame(route('documents.index'), NotificationDestination::url('company_document_removed'));
        $this->assertSame(route('passwords.index'), NotificationDestination::url('password_updated'));
        $this->assertSame(route('settings.index'), NotificationDestination::url('figma_token_expiring'));
        $this->assertSame(route('notifications.index'), NotificationDestination::url('unknown_event'));
    }

    public function test_push_diagnostic_reports_missing_configuration_and_subscriptions(): void
    {
        $user = User::factory()->create();
        $service = app(CentroNotificationService::class);

        config()->set('services.webpush.public_key', null);
        config()->set('services.webpush.private_key', null);
        $this->assertSame('not_configured', $service->testBrowserPush($user->id)['status']);

        config()->set('services.webpush.public_key', 'test-public-key');
        config()->set('services.webpush.private_key', 'test-private-key');
        $this->assertSame('no_subscription', $service->testBrowserPush($user->id)['status']);
        $this->assertSame(0, DB::table('push_subscriptions')->where('user_id', $user->id)->count());
    }

    public function test_push_test_endpoint_returns_a_real_diagnostic_status(): void
    {
        $user = User::factory()->create();
        config()->set('services.webpush.public_key', null);
        config()->set('services.webpush.private_key', null);

        $this->actingAs($user)->postJson(route('push.test'))
            ->assertOk()
            ->assertJson(['status' => 'not_configured', 'sent' => 0]);
    }
}
