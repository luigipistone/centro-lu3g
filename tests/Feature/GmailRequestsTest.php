<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GmailRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GmailRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(string $role = 'superadmin'): User
    {
        $u = User::factory()->create(['account_status' => 'active']);
        DB::table('user_roles')->insert(['id' => Str::uuid(), 'user_id' => $u->id, 'role' => $role]);
        return $u;
    }
    private function connection(User $u): object
    {
        DB::table('gmail_settings')->updateOrInsert(['id' => 1], ['client_id' => 'client', 'client_secret' => Crypt::encryptString('secret')]);
        $id = (string) Str::uuid();
        DB::table('gmail_connections')->insert(['id' => $id, 'user_id' => $u->id, 'email' => $u->email,
            'refresh_token' => Crypt::encryptString('refresh'), 'label_id' => 'Label_1', 'enabled' => true]);
        return DB::table('gmail_connections')->find($id);
    }
    private function message(string $id = 'm1', string $thread = 't1'): array
    {
        return ['id' => $id, 'threadId' => $thread, 'labelIds' => ['Label_1'], 'internalDate' => '1794474000000', 'payload' => [
            'mimeType' => 'text/plain', 'headers' => [['name' => 'Subject', 'value' => 'Modifiche sito'], ['name' => 'From', 'value' => 'Cliente <client@example.com>'], ['name' => 'Message-ID', 'value' => '<'.$id.'@example.com>']],
            'body' => ['data' => base64_encode('Aggiornare la pagina contatti.')]]];
    }
    public function test_import_is_idempotent_shared_and_does_not_create_tasks(): void
    {
        $a = $this->connection($this->manager()); $b = $this->connection($this->manager('admin'));
        $service = new GmailRequests;
        $service->ingest($a, $this->message()); $service->ingest($a, $this->message());
        $service->ingest($b, $this->message());
        $this->assertDatabaseCount('mail_requests', 2); $this->assertDatabaseCount('mail_request_messages', 2);
        $this->assertDatabaseCount('tasks', 0);
        $first = DB::table('mail_requests')->where('connection_id', $a->id)->first();
        $this->assertDatabaseHas('mail_requests', ['connection_id' => $b->id, 'duplicate_of' => $first->id]);
        DB::table('mail_requests')->where('id', $first->id)->update(['status' => 'converted']);
        $service->ingest($a, $this->message('m2'));
        $this->assertDatabaseHas('mail_requests', ['id' => $first->id, 'status' => 'converted', 'version' => 2, 'has_updates' => true]);
    }
    public function test_sync_reads_pages_and_unlabelled_replies_but_not_other_threads(): void
    {
        $c = $this->connection($this->manager()); $reply = $this->message('reply'); $reply['labelIds'] = ['INBOX'];
        Http::preventStrayRequests();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']),
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::sequence()->push(['messages' => [['id' => 'm1']], 'nextPageToken' => 'next'])->push(['messages' => [['id' => 'm2']]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m1?*' => Http::response($this->message()),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m2?*' => Http::response($this->message('m2')),
            'gmail.googleapis.com/gmail/v1/users/me/threads/t1?*' => Http::response(['messages' => [$this->message(), $reply]])]);
        (new GmailRequests)->sync($c->id);
        $this->assertDatabaseCount('mail_request_messages', 3); $this->assertDatabaseCount('mail_requests', 1);
        $this->assertNull(DB::table('gmail_connections')->value('last_error'));
        Http::assertSent(fn ($r) => ($r['pageToken'] ?? null) === 'next' && $r['labelIds'] === ['Label_1']);
        $this->assertDatabaseCount('tasks', 0);
    }
    public function test_failed_sync_retains_imported_messages_and_reports_error(): void
    {
        $c = $this->connection($this->manager());
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']),
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::sequence()->push(['messages' => [['id' => 'm1']], 'nextPageToken' => 'next'])->push([], 503),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m1?*' => Http::response($this->message())]);
        (new GmailRequests)->sync($c->id);
        $this->assertDatabaseCount('mail_request_messages', 1);
        $this->assertNotNull(DB::table('gmail_connections')->value('last_error'));
    }
    public function test_access_and_private_credentials(): void
    {
        $a = $this->manager(); $this->connection($a);
        $b = $this->manager('admin');
        $this->actingAs($b)->getJson('/requests/gmail')->assertOk()->assertJsonPath('connected', false)->assertJsonPath('client_id', '');
        $this->putJson('/requests/gmail/credentials', [])->assertForbidden();
        $this->actingAs($a)->getJson('/requests/gmail')->assertOk()->assertJsonMissingPath('refresh_token')->assertJsonMissingPath('client_secret');
        $this->actingAs($this->manager('editor'))->getJson('/requests/gmail')->assertForbidden();
        $this->get('/requests')->assertForbidden();
    }
    public function test_oauth_checks_identity_and_state_is_single_use(): void
    {
        $u = $this->manager(); $this->connection($u); $this->actingAs($u);
        $this->get('/requests/gmail/connect')->assertRedirect(); $state = session('gmail_requests_oauth.state');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'refresh_token' => 'newrefresh', 'scope' => GmailRequests::SCOPE]),
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => $u->email])]);
        $this->get('/requests/gmail/callback?state='.$state.'&code=code')->assertRedirect('/requests?connections=1');
        $this->assertSame('newrefresh', Crypt::decryptString(DB::table('gmail_connections')->value('refresh_token')));
        $this->get('/requests/gmail/callback?state='.$state.'&code=code')->assertForbidden();
        $this->get('/requests/gmail/connect'); $state = session('gmail_requests_oauth.state');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['refresh_token' => 'wrong', 'access_token' => 'access', 'scope' => GmailRequests::SCOPE]),
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'another@example.com'])]);
        $this->get('/requests/gmail/callback?state='.$state.'&code=code')->assertRedirect();
        $this->assertSame('newrefresh', Crypt::decryptString(DB::table('gmail_connections')->value('refresh_token')));
    }
    public function test_claim_version_and_conversion_are_atomic(): void
    {
        $u = $this->manager(); $c = $this->connection($u); (new GmailRequests)->ingest($c, $this->message());
        $id = DB::table('mail_requests')->value('id');
        $client = (string) Str::uuid(); $project = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $client, 'name' => 'CLIENTE', 'active' => true]);
        DB::table('projects')->insert(['id' => $project, 'name' => 'Sito web', 'client_id' => $client, 'status' => 'active', 'color' => '#ffffff']);
        $payload = ['version' => 1, 'action' => 'create', 'title' => 'Modifica', 'client_id' => $client, 'project_id' => $project, 'priority' => 'medium', 'assignee_ids' => [$u->id]];
        $this->actingAs($u)->postJson('/requests/'.$id.'/resolve', $payload)->assertConflict();
        $this->postJson('/requests/'.$id.'/claim')->assertOk();
        $this->actingAs($this->manager())->postJson('/requests/'.$id.'/claim')->assertConflict();
        $this->actingAs($u)->postJson('/requests/'.$id.'/resolve', array_replace($payload, ['version' => 0]))->assertConflict();
        $this->postJson('/requests/'.$id.'/resolve', array_replace($payload, ['project_id' => null]))->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 0);
        $this->postJson('/requests/'.$id.'/resolve', $payload)->assertOk();
        $this->postJson('/requests/'.$id.'/resolve', $payload)->assertConflict();
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('task_assignees', ['user_id' => $u->id]);
        $this->assertDatabaseHas('mail_requests', ['id' => $id, 'status' => 'converted']);
    }
    public function test_html_is_text_and_attachments_are_metadata_only(): void
    {
        $c = $this->connection($this->manager()); $m = $this->message();
        $m['payload']['mimeType'] = 'multipart/mixed'; unset($m['payload']['body']);
        $m['payload']['parts'] = [['mimeType' => 'text/html', 'body' => ['data' => base64_encode('<script>evil()</script><p>Richiesta &amp; dettagli</p>')]],
            ['filename' => 'brief.pdf', 'partId' => '1', 'body' => ['attachmentId' => 'attachment', 'size' => 100]]];
        (new GmailRequests)->ingest($c, $m);
        $message = DB::table('mail_request_messages')->first();
        $this->assertStringContainsString('Richiesta & dettagli', $message->body);
        $this->assertStringNotContainsString('evil', $message->body);
        $this->assertSame('brief.pdf', json_decode($message->attachments, true)[0]['name']);
    }
    public function test_client_and_project_suggestions_require_unique_matches(): void
    {
        $c = $this->connection($this->manager()); $client = (string) Str::uuid(); $project = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $client, 'name' => 'CLIENTE', 'email' => 'client@example.com', 'active' => true]);
        DB::table('projects')->insert(['id' => $project, 'name' => 'Sito', 'client_id' => $client, 'status' => 'active', 'color' => '#fff']);
        (new GmailRequests)->ingest($c, $this->message());
        $this->assertDatabaseHas('mail_requests', ['thread_id' => 't1', 'suggested_client_id' => $client, 'suggested_project_id' => $project]);
        DB::table('clients')->insert(['id' => (string) Str::uuid(), 'name' => 'ALTRO', 'email' => 'client@example.com', 'active' => true]);
        (new GmailRequests)->ingest($c, $this->message('m2', 't2'));
        $this->assertDatabaseHas('mail_requests', ['thread_id' => 't2', 'suggested_client_id' => null, 'suggested_project_id' => null]);
    }
    public function test_disabled_connection_and_revoked_manager_are_not_polled(): void
    {
        $u = $this->manager('admin'); $c = $this->connection($u); Http::fake();
        DB::table('gmail_connections')->where('id', $c->id)->update(['enabled' => false]);
        (new GmailRequests)->sync($c->id);
        DB::table('gmail_connections')->where('id', $c->id)->update(['enabled' => true]);
        DB::table('user_roles')->where('user_id', $u->id)->update(['role' => 'editor']);
        (new GmailRequests)->sync($c->id);
        Http::assertNothingSent();
    }
    public function test_google_rate_limit_preserves_label_and_pauses_sync(): void
    {
        $u = $this->manager(); $c = $this->connection($u);
        DB::table('gmail_connections')->where('id', $c->id)->update(['label_name' => 'Invia al Centro']);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']),
            'gmail.googleapis.com/*' => Http::response(['error' => ['errors' => [['reason' => 'rateLimitExceeded']]]], 403)]);
        $this->actingAs($u)->getJson('/requests/gmail/labels')->assertOk()->assertJsonPath('labels.0.label', 'Invia al Centro')->assertJsonStructure(['warning']);
        $this->assertTrue(GmailRequests::coolingDown($c->id));
        (new GmailRequests)->sync($c->id);
        $this->getJson('/requests/gmail/labels')->assertOk();
        Http::assertSentCount(2);
    }
}
