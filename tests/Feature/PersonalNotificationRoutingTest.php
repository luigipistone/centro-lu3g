<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CentroNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalNotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_absence_reaches_only_subject_assigned_manager_and_superadmin(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->create();
        $unrelatedManager = User::factory()->create();
        $superadmin = User::factory()->create();
        foreach ([[$employee, 'editor'], [$manager, 'admin'], [$unrelatedManager, 'admin'], [$superadmin, 'superadmin']] as [$user, $role]) {
            DB::table('user_roles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role]);
        }
        DB::table('profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $employee->id, 'manager_user_id' => $manager->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $recipients = app(CentroNotificationService::class)->absenceRecipientIds($employee->id);

        $this->assertEqualsCanonicalizing([$employee->id, $manager->id, $superadmin->id], $recipients);
    }

    public function test_password_notifications_follow_the_recipients_preference(): void
    {
        $actor = User::factory()->create();
        $enabled = User::factory()->create();
        $disabled = User::factory()->create();
        DB::table('notification_preferences')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $disabled->id, 'category' => 'passwords',
            'in_app' => false, 'browser' => false, 'mail' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(CentroNotificationService::class)->notifyUsers([$enabled->id, $disabled->id], $actor->id, 'password_updated', 'Una credenziale condivisa è stata aggiornata.');

        $this->assertDatabaseHas('notifications', ['user_id' => $enabled->id, 'type' => 'password_updated']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $disabled->id, 'type' => 'password_updated']);
    }
}
