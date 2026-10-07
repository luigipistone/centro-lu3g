<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PayslipRecognitionService;
use App\Services\ScheduledCompanyMessageService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payslip_recognition_extracts_period_and_employee_from_compact_text(): void
    {
        $text = '097620309644984939309SETTEMBRE 202600011565'."\n".
            '000024 SAPONARA GRETA SPNGRT95L63F205Q 24H011';
        $result = app(PayslipRecognitionService::class)->recognizeText($text);

        $this->assertSame('Compenso Settembre 2026', $result['title']);
        $this->assertSame('SAPONARA GRETA', $result['name']);
        $this->assertSame('SPNGRT95L63F205Q', $result['fiscal_code']);
    }

    public function test_payslip_recognition_page_and_upload_are_superadmin_only(): void
    {
        $manager = User::factory()->create();
        $superadmin = User::factory()->create();
        $this->role($manager, 'admin');
        $this->role($superadmin, 'superadmin');

        $this->actingAs($manager)->get(route('documents.compensi.recognition'))->assertForbidden();
        $this->actingAs($manager)->postJson(route('documents.compensi.recognition.preview'), [])->assertForbidden();
        $this->actingAs($manager)->postJson(route('documents.compensi.recognition.publish'), [])->assertForbidden();
        $this->actingAs($superadmin)->get(route('documents.compensi.recognition'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Centro/PayslipRecognition')->has('users'));
    }

    public function test_payslip_preview_requires_explicit_publication_and_blocks_duplicates(): void
    {
        Storage::fake('local');
        $superadmin = User::factory()->create();
        $recipient = User::factory()->create(['name' => 'Greta Saponara']);
        $this->role($superadmin, 'superadmin');
        DB::table('profiles')->where('user_id', $recipient->id)->update(['fiscal_code' => 'SPNGRT95L63F205Q']);

        $makePdf = function (): UploadedFile {
            $pdf = new Dompdf;
            $pdf->loadHtml('<p>SETTEMBRE 2026</p><p>000024 SAPONARA GRETA SPNGRT95L63F205Q</p>');
            $pdf->render();
            $path = tempnam(sys_get_temp_dir(), 'payslip');
            file_put_contents($path, $pdf->output());

            return new UploadedFile($path, 'CED.9.26-9.pdf', 'application/pdf', null, true);
        };

        $this->actingAs($superadmin)->postJson(route('documents.compensi.recognition.preview'), [
            'files' => [$makePdf()],
        ])->assertOk()->assertJsonPath('rows.0.user_id', $recipient->id)
            ->assertJsonPath('rows.0.title', 'Compenso Settembre 2026');
        $this->assertDatabaseCount('company_documents', 0);

        $this->actingAs($superadmin)->post(route('documents.compensi.recognition.publish'), [
            'files' => [$makePdf()], 'user_ids' => [$recipient->id], 'selected' => [true],
        ])->assertSessionHasErrors('publication_confirmed');
        $this->assertDatabaseCount('company_documents', 0);

        $this->actingAs($superadmin)->postJson(route('documents.compensi.recognition.publish'), [
            'files' => [$makePdf()], 'user_ids' => [$recipient->id], 'selected' => [true],
            'publication_confirmed' => true,
        ])->assertOk()->assertJsonPath('published', 1);
        $this->assertDatabaseHas('company_documents', ['title' => 'Compenso Settembre 2026', 'category' => 'compensi', 'audience' => 'users']);
        $this->assertDatabaseCount('company_document_user', 1);

        $this->actingAs($superadmin)->post(route('documents.compensi.recognition.publish'), [
            'files' => [$makePdf()], 'user_ids' => [$recipient->id], 'selected' => [true],
            'publication_confirmed' => true,
        ])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('company_documents', 1);
    }

    public function test_only_superadmin_can_publish_compensation_folder_with_monthly_titles(): void
    {
        Storage::fake('local');
        $superadmin = User::factory()->create();
        $manager = User::factory()->create();
        $recipient = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($manager, 'admin');
        $this->role($recipient, 'editor');
        $payload = [
            'user_id' => $recipient->id,
            'publication_confirmed' => true,
            'files' => [
                UploadedFile::fake()->create('Alessia-ced.1.25.pdf', 12, 'application/pdf'),
                UploadedFile::fake()->create('ced.2.25.pdf', 12, 'application/pdf'),
            ],
        ];

        $this->actingAs($manager)->post(route('documents.compensi.bulk.store'), $payload)->assertForbidden();
        $this->actingAs($manager)->postJson(route('documents.compensi.bulk.check'), [
            'user_id' => $recipient->id, 'titles' => ['Compenso Gennaio 2025'],
        ])->assertForbidden();
        $this->assertDatabaseCount('company_documents', 0);

        $this->actingAs($superadmin)->post(route('documents.compensi.bulk.store'), $payload)
            ->assertRedirect(route('documents.users.show', $recipient->id))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('company_documents', ['title' => 'Compenso Gennaio 2025', 'category' => 'compensi', 'document_year' => 2025, 'audience' => 'users']);
        $this->assertDatabaseHas('company_documents', ['title' => 'Compenso Febbraio 2025', 'category' => 'compensi', 'document_year' => 2025]);
        $this->assertDatabaseCount('company_document_user', 2);
        $this->assertDatabaseHas('notifications', ['user_id' => $recipient->id, 'type' => 'company_document_created']);
        $this->actingAs($superadmin)->postJson(route('documents.compensi.bulk.check'), [
            'user_id' => $recipient->id,
            'titles' => ['Compenso Gennaio 2025', 'Compenso Marzo 2025'],
        ])->assertOk()->assertJsonPath('existing.0', 'Compenso Gennaio 2025')->assertJsonCount(1, 'existing');

        $this->actingAs($superadmin)->post(route('documents.compensi.bulk.store'), [
            'user_id' => $recipient->id,
            'publication_confirmed' => true,
            'files' => [UploadedFile::fake()->create('ced.1.25.pdf', 12, 'application/pdf')],
        ])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('company_documents', 2);
    }

    public function test_compensation_folder_rejects_invalid_month_without_partial_publication(): void
    {
        Storage::fake('local');
        $superadmin = User::factory()->create();
        $recipient = User::factory()->create();
        $this->role($superadmin, 'superadmin');

        $this->actingAs($superadmin)->post(route('documents.compensi.bulk.store'), [
            'user_id' => $recipient->id,
            'publication_confirmed' => true,
            'files' => [
                UploadedFile::fake()->create('ced.1.25.pdf', 12, 'application/pdf'),
                UploadedFile::fake()->create('ced.13.25.pdf', 12, 'application/pdf'),
            ],
        ])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('company_documents', 0);

        $this->actingAs($superadmin)->post(route('documents.compensi.bulk.store'), [
            'user_id' => $recipient->id,
            'publication_confirmed' => true,
            'files' => [UploadedFile::fake()->create('Alessia-ced.4.25-extra.pdf', 12, 'application/pdf')],
        ])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('company_documents', 0);
    }

    public function test_manager_document_subsections_follow_individual_permissions(): void
    {
        $manager = User::factory()->create();
        $this->role($manager, 'admin');

        foreach (['documents.view', 'documents.manage'] as $permission) {
            DB::table('role_permissions')->where('role', 'admin')->where('permission', $permission)->update(['allowed' => true]);
        }

        $this->actingAs($manager)->get(route('documents.list'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewUserOverview', false)
                ->where('canViewMessages', true)
                ->where('canViewGroups', true));

        DB::table('role_permissions')->where('role', 'admin')->where('permission', 'documents.messages.view')->update(['allowed' => false]);
        DB::table('role_permissions')->where('role', 'admin')->where('permission', 'documents.groups.view')->update(['allowed' => false]);

        $this->actingAs($manager)->get(route('documents.messages'))->assertForbidden();
        $this->actingAs($manager)->get(route('documents.groups'))->assertForbidden();
        $this->actingAs($manager)->get(route('documents.list'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewMessages', false)
                ->where('canViewGroups', false));
    }

    public function test_admin_can_publish_document_for_user_and_user_can_mark_it_read(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['name' => 'Admin']);
        $user = User::factory()->create(['name' => 'Mario Rossi']);
        $this->role($admin, 'superadmin');
        $this->role($user, 'editor');
        DB::table('role_permissions')->where('role', 'admin')->where('permission', 'documents.manage')->update(['allowed' => true]);

        $this
            ->actingAs($admin)
            ->post(route('documents.store'), [
                'title' => 'Policy interna',
                'description' => 'Da leggere con attenzione.',
                'category' => 'documenti_vari',
                'document_year' => 2024,
                'audience' => 'users',
                'user_ids' => [$user->id],
                'publication_confirmed' => true,
                'file' => UploadedFile::fake()->create('policy.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.list'))
            ->assertSessionHasNoErrors();

        $documentId = DB::table('company_documents')->value('id');
        $this->assertNotNull($documentId);
        $this->assertDatabaseHas('company_documents', ['id' => $documentId, 'document_year' => 2024]);
        $this->actingAs($admin)->get(route('documents.list'))
            ->assertInertia(fn (Assert $page) => $page->has('documents', 0));
        $this->actingAs($user)->get(route('documents.list'))
            ->assertInertia(fn (Assert $page) => $page->has('documents', 1)->where('documents.0.id', $documentId));
        $this->assertDatabaseHas('company_document_reads', [
            'company_document_id' => $documentId,
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'company_document_id' => $documentId,
            'type' => 'company_document_created',
            'read' => false,
        ]);

        $this
            ->actingAs($user)
            ->get(route('documents.show', $documentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centro/DocumentShow')
                ->where('document.id', $documentId)
                ->where('document.user_read_at', null)
            );

        $this->actingAs($admin)->get(route('documents.list'))
            ->assertInertia(fn (Assert $page) => $page->where('documentUsers', function ($users) use ($user) {
                $overview = collect($users)->firstWhere('id', $user->id);

                return data_get($overview, 'opened_count') === 1 && data_get($overview, 'unread_count') === 0;
            }));

        $this
            ->actingAs($user)
            ->post(route('documents.read', $documentId))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(DB::table('company_document_reads')
            ->where('company_document_id', $documentId)
            ->where('user_id', $user->id)
            ->value('read_at'));

        $this->actingAs($admin)->get(route('documents.list'))
            ->assertInertia(fn (Assert $page) => $page->where('documentUsers', function ($users) use ($user) {
                $overview = collect($users)->firstWhere('id', $user->id);

                return data_get($overview, 'read_count') === 1 && data_get($overview, 'opened_count') === 0;
            }));

        $this
            ->actingAs($admin)
            ->get(route('documents.users.show', $user->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centro/DocumentUserShow')
                ->where('user.id', $user->id)
                ->has('documents', 1)
            );
    }

    public function test_all_documents_list_only_includes_documents_for_everyone(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($employee, 'editor');

        foreach ([['Personale', 'users'], ['Generale', 'all']] as [$title, $audience]) {
            $this->actingAs($admin)->post(route('documents.store'), [
                'title' => $title, 'category' => 'documenti_vari', 'audience' => $audience,
                'user_ids' => $audience === 'users' ? [$employee->id] : [],
                'publication_confirmed' => true,
                'file' => UploadedFile::fake()->create($title.'.pdf', 10, 'application/pdf'),
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($admin)->get(route('documents.list'))
            ->assertInertia(fn (Assert $page) => $page->has('documents', 1)->where('documents.0.title', 'Generale'));
        $this->actingAs($employee)->get(route('documents.list'))
            ->assertInertia(fn (Assert $page) => $page->has('documents', 2));
    }

    public function test_admin_cannot_create_empty_document_group(): void
    {
        $admin = User::factory()->create();
        $this->role($admin, 'admin');

        $this
            ->actingAs($admin)
            ->post(route('document-groups.store'), [
                'name' => 'Team vuoto',
                'description' => '',
                'user_ids' => [],
            ])
            ->assertSessionHasErrors(['user_ids']);

        $this->assertDatabaseCount('document_groups', 0);
    }

    public function test_document_edit_preserves_previous_pdf_and_resets_read_confirmation(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($user, 'editor');

        $this->actingAs($admin)->post(route('documents.store'), [
            'title' => 'Prima versione', 'category' => 'documenti_vari', 'audience' => 'users',
            'user_ids' => [$user->id], 'publication_confirmed' => true,
            'file' => UploadedFile::fake()->create('prima.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $id = DB::table('company_documents')->value('id');
        $oldPath = DB::table('company_documents')->value('file_path');
        $this->actingAs($user)->post(route('documents.read', $id))->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('documents.update', $id), [
            'title' => 'Nuova versione', 'category' => 'documenti_vari', 'document_year' => 2026,
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('documents.update', $id), [
            'title' => 'Nuova versione', 'description' => 'Testo aggiornato',
            'category' => 'documenti_vari', 'document_year' => 2026,
            'file' => UploadedFile::fake()->create('seconda.pdf', 12, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('company_documents', ['id' => $id, 'title' => 'Nuova versione', 'file_name' => 'seconda.pdf']);
        $this->assertDatabaseHas('company_document_versions', ['company_document_id' => $id, 'version' => 1, 'file_path' => $oldPath]);
        $this->assertDatabaseHas('company_document_reads', ['company_document_id' => $id, 'user_id' => $user->id, 'read_at' => null]);
        Storage::disk('local')->assertExists($oldPath);
        $versionId = DB::table('company_document_versions')->value('id');
        $this->actingAs($admin)->get(route('documents.versions.file', [$id, $versionId]))->assertOk();
        $this->actingAs($user)->get(route('documents.versions.file', [$id, $versionId]))->assertForbidden();
    }

    public function test_cloning_document_prefills_metadata_but_requires_a_new_pdf(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($user, 'editor');

        $this->actingAs($admin)->post(route('documents.store'), [
            'title' => 'Originale', 'description' => 'Descrizione', 'category' => 'documenti_vari',
            'document_year' => 2025, 'audience' => 'users', 'user_ids' => [$user->id],
            'publication_confirmed' => true,
            'file' => UploadedFile::fake()->create('originale.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $id = DB::table('company_documents')->value('id');
        $originalPath = DB::table('company_documents')->value('file_path');

        $this->actingAs($user)->get(route('documents.clone', $id))->assertForbidden();
        $this->actingAs($admin)->get(route('documents.clone', $id))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Centro/DocumentClone')
            ->where('source.title', 'Originale')
            ->where('source.document_year', 2025)
            ->where('source.user_ids.0', $user->id)
            ->missing('source.file_path'));
        $this->actingAs($admin)->get(route('documents.show', ['id' => $id, 'from_user' => $user->id]))
            ->assertInertia(fn (Assert $page) => $page->where('returnUserId', $user->id));
        $this->actingAs($admin)->get(route('documents.clone', ['id' => $id, 'from_user' => $user->id]))
            ->assertInertia(fn (Assert $page) => $page->where('returnUserId', $user->id));

        $payload = [
            'title' => 'Copia', 'description' => 'Descrizione', 'category' => 'documenti_vari',
            'document_year' => 2025, 'audience' => 'users', 'user_ids' => [$user->id],
            'publication_confirmed' => true,
        ];
        $this->actingAs($admin)->post(route('documents.store'), $payload)->assertSessionHasErrors('file');
        $this->actingAs($admin)->post(route('documents.store', ['from_user' => $user->id]), [
            ...$payload, 'file' => UploadedFile::fake()->create('copia.pdf', 11, 'application/pdf'),
        ])->assertRedirect(route('documents.users.show', $user->id))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('company_documents', 2);
        $this->assertNotEquals($originalPath, DB::table('company_documents')->where('title', 'Copia')->value('file_path'));
    }

    public function test_sensitive_document_requires_one_recipient_and_manager_access_is_explicit(): void
    {
        Storage::fake('local');
        $superadmin = User::factory()->create();
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $this->role($superadmin, 'superadmin');
        $this->role($manager, 'admin');
        $this->role($employee, 'editor');

        $payload = [
            'title' => 'Busta paga', 'category' => 'compensi', 'audience' => 'all',
            'publication_confirmed' => true,
            'file' => UploadedFile::fake()->create('cedolino.pdf', 10, 'application/pdf'),
        ];
        $this->actingAs($superadmin)->post(route('documents.store'), $payload)->assertSessionHasErrors('audience');
        $this->assertDatabaseCount('company_documents', 0);

        $payload['audience'] = 'users';
        $payload['user_ids'] = [$employee->id];
        $this->actingAs($manager)->post(route('documents.store'), $payload)->assertForbidden();
        $payload['publication_confirmed'] = false;
        $this->actingAs($superadmin)->post(route('documents.store'), $payload)->assertSessionHasErrors('publication_confirmed');
        $payload['publication_confirmed'] = true;
        $this->actingAs($superadmin)->post(route('documents.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $id = DB::table('company_documents')->value('id');

        $this->actingAs($manager)->get(route('documents.show', $id))->assertForbidden();
        $this->actingAs($manager)->get(route('documents.file', $id))->assertForbidden();
        $this->actingAs($manager)->get(route('documents.users.show', $employee->id))->assertForbidden();
        $this->actingAs($manager)->get(route('documents.list'))->assertInertia(fn (Assert $page) => $page->has('documents', 0));

        $this->actingAs($superadmin)->post(route('documents.manager-access.store', $id), ['user_id' => $manager->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('documents.file', $id))->assertOk();
        $this->actingAs($employee)->get(route('documents.file', $id))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'accesso_file_documento', 'subject_id' => $id, 'user_id' => $employee->id]);

        $this->actingAs($superadmin)->delete(route('documents.manager-access.destroy', [$id, $manager->id]))->assertRedirect();
        $this->actingAs($manager)->get(route('documents.file', $id))->assertForbidden();

        DB::table('company_documents')->where('id', $id)->update(['audience' => 'all']);
        $this->actingAs($manager)->get(route('documents.file', $id))->assertForbidden();
        $this->actingAs($employee)->get(route('documents.file', $id))->assertForbidden();
    }

    public function test_scheduled_message_is_published_only_when_due_and_repeats(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 09:00:00', 'Europe/Rome'));
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $this->role($admin, 'superadmin');
        $this->role($user, 'editor');

        $this->actingAs($admin)->post(route('document-messages.schedules.store'), [
            'title' => 'Promemoria', 'body' => 'Controlla la bacheca.', 'audience' => 'users',
            'user_ids' => [$user->id], 'scheduled_at' => '2026-09-29T09:00', 'recurrence' => 'daily',
            'ends_on' => '2026-09-30',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $service = app(ScheduledCompanyMessageService::class);
        $this->assertSame(0, $service->publishDue());
        $this->assertDatabaseCount('company_messages', 0);

        $this->travelTo(Carbon::parse('2026-09-29 09:01:00', 'Europe/Rome'));
        $this->assertSame(1, $service->publishDue());
        $this->assertSame(0, $service->publishDue());
        $this->assertDatabaseCount('company_messages', 1);
        $this->assertDatabaseHas('company_message_reads', ['user_id' => $user->id, 'read_at' => null]);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'company_message_created']);

        $this->travelTo(Carbon::parse('2026-09-30 09:01:00', 'Europe/Rome'));
        $this->assertSame(1, $service->publishDue());
        $this->assertDatabaseCount('company_messages', 2);
        $this->assertDatabaseHas('company_message_schedules', ['active' => false]);
    }

    private function role(User $user, string $role): void
    {
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
