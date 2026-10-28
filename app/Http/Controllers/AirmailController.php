<?php

namespace App\Http\Controllers;

use App\Services\AirmailStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AirmailController extends Controller
{
    public function index(Request $request, AirmailStorage $storage): Response
    {
        return Inertia::render('Centro/Airmail', [
            'configured' => $storage->isConfigured(),
            'isSuperadmin' => $this->isSuperadmin($request),
            'transfers' => DB::table('airmail_transfers')
                ->where('user_id', $request->user()->id)
                ->whereIn('status', ['uploading', 'ready'])
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')->limit(40)->get()
                ->map(fn ($transfer) => $this->formatTransfer($transfer))->all(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);
        $data = $request->validate(['password' => ['required', 'string', 'max:1000']]);
        $existing = DB::table('airmail_settings')->first();
        DB::table('airmail_settings')->updateOrInsert(
            ['id' => $existing->id ?? (string) Str::uuid()],
            ['encrypted_password' => Crypt::encryptString($data['password']), 'created_at' => $existing->created_at ?? now(), 'updated_at' => now()],
        );

        return back()->with('status', 'Accesso Airmail salvato. Verifica la connessione prima di inviare file.');
    }

    public function testSettings(Request $request, AirmailStorage $storage): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);
        try {
            $storage->checkConnection();
        } catch (\Throwable $exception) {
            Log::warning('Verifica Airmail fallita.', ['reason' => $exception->getMessage()]);
            return back()->withErrors(['airmail_test' => 'Connessione non riuscita. Verifica la password e riprova.']);
        }

        return back()->with('status', 'Connessione Airmail verificata.');
    }

    public function create(Request $request, AirmailStorage $storage): JsonResponse
    {
        abort_unless($storage->isConfigured(), 503);
        $validator = Validator::make($request->all(), [
            'mode' => ['required', 'in:send,link'],
            'recipient_email' => ['nullable', 'required_if:mode,send', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'expires_days' => ['required', 'integer', 'in:1,3,7'],
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*.name' => ['required', 'string', 'max:200'],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:'.config('airmail.max_bytes')],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->toArray()], 422);
        }
        $data = $validator->validated();
        $total = array_sum(array_column($data['files'], 'size'));
        if ($total > config('airmail.max_bytes')) {
            return response()->json(['errors' => ['files' => ['Massimo 3 GB complessivi per invio.']]], 422);
        }
        $active = DB::table('airmail_transfers')->whereIn('status', ['uploading', 'ready'])->where('expires_at', '>', now());
        if ((clone $active)->count() >= 30 || (clone $active)->where('user_id', $request->user()->id)->count() >= 5) {
            return response()->json(['errors' => ['files' => ['Limite di invii attivi raggiunto. Riprova dopo la scadenza o elimina un invio.']]], 422);
        }

        $id = (string) Str::uuid();
        try {
            $storage->createTransferFolder($id);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['errors' => ['files' => ['Storage non disponibile. Riprova tra poco.']]], 503);
        }
        $files = [];
        try {
            DB::transaction(function () use ($request, $data, $id, &$files) {
                DB::table('airmail_transfers')->insert([
                    'id' => $id, 'user_id' => $request->user()->id,
                    'mode' => $data['mode'], 'recipient_email' => $data['mode'] === 'send' ? $data['recipient_email'] : null,
                    'message' => $data['message'] ?? null,
                    'status' => 'uploading', 'expires_at' => now()->addDays($data['expires_days']),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($data['files'] as $index => $input) {
                    $fileId = (string) Str::uuid();
                    $originalName = basename(str_replace('\\', '/', $input['name']));
                    $originalName = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName));
                    $originalName = $originalName !== '' ? $originalName : 'file';
                    $storedName = sprintf('%02d-', $index + 1).
                        (substr(trim(preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($originalName)), '. '), 0, 150) ?: 'file');
                    DB::table('airmail_files')->insert([
                        'id' => $fileId, 'transfer_id' => $id,
                        'original_name' => $originalName, 'stored_name' => $storedName,
                        'size' => $input['size'], 'status' => 'uploading',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $files[] = ['id' => $fileId, 'name' => $originalName, 'size' => $input['size'], 'uploaded_chunks' => 0];
                }
            });
        } catch (\Throwable $exception) {
            try {
                $storage->removeTransfer((object) ['id' => $id, 'share_id' => null], []);
            } catch (\Throwable $cleanup) {
                report($cleanup);
            }
            throw $exception;
        }

        return response()->json(['id' => $id, 'files' => $files], 201);
    }

    public function uploadChunk(Request $request, AirmailStorage $storage, string $id, string $fileId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'index' => ['required', 'integer', 'min:1', 'max:10000'],
            'chunk' => ['required', 'file', 'max:16384'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->toArray()], 422);
        }
        $data = $validator->validated();
        $result = DB::transaction(function () use ($request, $storage, $id, $fileId, $data) {
            $transfer = $this->ownedTransfer($request, $id, true);
            abort_unless($transfer->status === 'uploading' && Carbon::parse($transfer->expires_at)->isFuture(), 409);
            $file = DB::table('airmail_files')->where('id', $fileId)->where('transfer_id', $id)->lockForUpdate()->first();
            abort_unless($file, 404);
            $maxChunks = (int) ceil($file->size / config('airmail.chunk_bytes'));
            $index = (int) $data['index'];
            abort_if($index > $maxChunks, 422);
            $expected = min(config('airmail.chunk_bytes'), $file->size - (($index - 1) * config('airmail.chunk_bytes')));
            abort_if($data['chunk']->getSize() !== $expected, 422, 'Dimensione del blocco non valida.');
            abort_if($index > $file->uploaded_chunks + 1, 409, 'Carica prima i blocchi precedenti.');

            if ($index === $file->uploaded_chunks + 1) {
                $storage->uploadChunk($transfer, $file, $index, $data['chunk']->getRealPath());
                DB::table('airmail_files')->where('id', $fileId)->update(['uploaded_chunks' => $index, 'updated_at' => now()]);
            }
            if ($index === $maxChunks && $file->status !== 'done') {
                $storage->finishFile($transfer, $file);
                DB::table('airmail_files')->where('id', $fileId)->update(['status' => 'done', 'updated_at' => now()]);
            }

            return ['uploaded_chunks' => $index, 'complete' => $index === $maxChunks];
        });

        return response()->json($result);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $transfer = $this->ownedTransfer($request, $id);
        $files = DB::table('airmail_files')->where('transfer_id', $id)->orderBy('stored_name')->get(['id', 'original_name as name', 'size', 'uploaded_chunks', 'status']);
        return response()->json(['transfer' => $this->formatTransfer($transfer), 'files' => $files]);
    }

    public function publish(Request $request, AirmailStorage $storage, string $id): JsonResponse
    {
        $transfer = DB::transaction(function () use ($request, $storage, $id) {
            $transfer = $this->ownedTransfer($request, $id, true);
            abort_unless($transfer->status === 'uploading' && Carbon::parse($transfer->expires_at)->isFuture(), 409);
            abort_if(DB::table('airmail_files')->where('transfer_id', $id)->where('status', '!=', 'done')->exists(), 409, 'Completa tutti i caricamenti.');
            $token = Str::random(48);
            DB::table('airmail_transfers')->where('id', $id)->update([
                'status' => 'ready',
                'token_hash' => hash('sha256', $token), 'encrypted_token' => Crypt::encryptString($token), 'updated_at' => now(),
            ]);
            return DB::table('airmail_transfers')->where('id', $id)->first();
        });

        $emailSent = $transfer->mode === 'send' && $this->sendEmail($request, $transfer);
        if ($emailSent) {
            DB::table('airmail_transfers')->where('id', $id)->update(['email_sent_at' => now()]);
        }

        return response()->json(['transfer' => $this->formatTransfer($transfer), 'email_sent' => $emailSent]);
    }

    public function destroy(Request $request, AirmailStorage $storage, string $id): \Illuminate\Http\Response
    {
        $transfer = $this->ownedTransfer($request, $id);
        if (! in_array($transfer->status, ['deleted', 'expired'], true)) {
            $fileIds = DB::table('airmail_files')->where('transfer_id', $id)->pluck('id')->all();
            $storage->removeTransfer($transfer, $fileIds);
            DB::table('airmail_transfers')->where('id', $id)->update(['status' => 'deleted', 'updated_at' => now()]);
        }
        return response()->noContent();
    }

    public function open(string $token): View
    {
        $transfer = $this->publicTransfer($token);
        $files = DB::table('airmail_files')->where('transfer_id', $transfer->id)->where('status', 'done')
            ->orderBy('stored_name')->get(['id', 'original_name', 'size']);
        $sender = DB::table('users')->where('id', $transfer->user_id)->value('name') ?: 'Il Centro';

        return view('airmail-public', compact('transfer', 'files', 'sender', 'token'));
    }

    public function download(Request $request, AirmailStorage $storage, string $token, string $fileId): StreamedResponse
    {
        $transfer = $this->publicTransfer($token);
        abort_unless(Str::isUuid($fileId), 404);
        $file = DB::table('airmail_files')->where('id', $fileId)->where('transfer_id', $transfer->id)
            ->where('status', 'done')->first();
        abort_unless($file, 404);

        $range = $request->header('Range');
        if ($range !== null) {
            abort_unless(preg_match('/^bytes=(\d+)-(\d*)$/', $range), 416);
            $start = (int) explode('-', substr($range, 6))[0];
            abort_if($start >= $file->size, 416);
        }
        $upstream = $storage->openFile($transfer, $file, $range);
        abort_unless(in_array($upstream->status(), [200, 206], true), 502);

        $contentLength = $file->size;
        $contentRange = null;
        if ($upstream->status() === 206) {
            $contentRange = $upstream->header('Content-Range');
            abort_unless(preg_match('/^bytes (\d+)-(\d+)\/(\d+)$/', $contentRange ?? '', $parts), 502);
            abort_unless((int) $parts[3] === $file->size, 502);
            $contentLength = (int) $parts[2] - (int) $parts[1] + 1;
        }

        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($file->original_name)) ?: 'file';
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->original_name, $fallback),
            'Content-Length' => (string) $contentLength,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'X-Accel-Buffering' => 'no',
        ];
        if ($upstream->status() === 206) {
            $headers['Content-Range'] = $contentRange;
        }

        return response()->stream(function () use ($upstream) {
            @set_time_limit(0);
            $body = $upstream->toPsrResponse()->getBody();
            try {
                while (! $body->eof() && ! connection_aborted()) {
                    echo $body->read(1024 * 1024);
                    flush();
                }
            } finally {
                $body->close();
            }
        }, $upstream->status(), $headers);
    }

    private function publicTransfer(string $token): object
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{48}$/', $token), 404);
        $transfer = DB::table('airmail_transfers')->where('token_hash', hash('sha256', $token))->first();
        abort_unless($transfer && $transfer->status === 'ready' && Carbon::parse($transfer->expires_at)->isFuture(), 404);
        return $transfer;
    }

    private function ownedTransfer(Request $request, string $id, bool $lock = false): object
    {
        abort_unless(Str::isUuid($id), 404);
        $query = DB::table('airmail_transfers')->where('id', $id)->where('user_id', $request->user()->id);
        $transfer = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($transfer, 404);
        return $transfer;
    }

    private function formatTransfer(object $transfer): array
    {
        $expired = Carbon::parse($transfer->expires_at)->isPast();
        return [
            'id' => $transfer->id, 'mode' => $transfer->mode, 'recipient_email' => $transfer->recipient_email,
            'status' => $expired && in_array($transfer->status, ['uploading', 'ready'], true) ? 'expired' : $transfer->status,
            'expires_at' => $transfer->expires_at,
            'created_at' => $transfer->created_at, 'email_sent' => filled($transfer->email_sent_at),
            'url' => $transfer->encrypted_token && $transfer->status === 'ready' && ! $expired
                ? route('airmail.open', Crypt::decryptString($transfer->encrypted_token)) : null,
        ];
    }

    private function isSuperadmin(Request $request): bool
    {
        return DB::table('user_roles')->where('user_id', $request->user()->id)->where('role', 'superadmin')->exists();
    }

    private function sendEmail(Request $request, object $transfer): bool
    {
        $settings = DB::table('email_settings')->first();
        if (! $settings?->smtp_enabled || ! $settings->smtp_host || ! $settings->smtp_from_email) {
            return false;
        }
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings->smtp_host);
        Config::set('mail.mailers.smtp.port', $settings->smtp_port ?: 587);
        Config::set('mail.mailers.smtp.username', $settings->smtp_username);
        Config::set('mail.mailers.smtp.password', $settings->smtp_password);
        Config::set('mail.mailers.smtp.encryption', $settings->smtp_secure ? 'tls' : null);
        Config::set('mail.from.address', $settings->smtp_from_email);
        Config::set('mail.from.name', $settings->smtp_from_name ?: 'Il Centro');

        $url = route('airmail.open', Crypt::decryptString($transfer->encrypted_token));
        $text = $request->user()->name." ti ha inviato dei file con Airmail.\n\n";
        if ($transfer->message) {
            $text .= $transfer->message."\n\n";
        }
        $text .= "Apri e scarica: {$url}\n\nIl link scade il ".\Illuminate\Support\Carbon::parse($transfer->expires_at)->timezone('Europe/Rome')->format('d/m/Y H:i').'.';
        try {
            Mail::raw($text, fn ($mail) => $mail->to($transfer->recipient_email)->subject('File ricevuti con Airmail - Il Centro'));
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Invio email Airmail non riuscito.', ['transfer_id' => $transfer->id, 'reason' => $exception->getMessage()]);
            return false;
        }
    }
}
