<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use RuntimeException;

class AirmailStorage
{
    public function isConfigured(): bool
    {
        return filled(DB::table('airmail_settings')->value('encrypted_password'));
    }

    public function checkConnection(): void
    {
        $response = $this->client()->withHeaders(['Depth' => '0'])->send('PROPFIND', $this->filesUrl());
        $this->requireStatus($response, [207], 'Connessione allo storage non riuscita.');
    }

    public function createTransferFolder(string $transferId): void
    {
        $this->createDirectory($this->filesUrl(config('airmail.folder')));
        $this->createDirectory($this->filesUrl(config('airmail.folder').'/'.$transferId));
    }

    public function uploadChunk(object $transfer, object $file, int $index, string $path): void
    {
        $destination = $this->filesUrl(config('airmail.folder').'/'.$transfer->id.'/'.$file->stored_name);
        $upload = $this->uploadsUrl($file->id);
        if ($index === 1 && (int) $file->uploaded_chunks === 0) {
            $response = $this->client()->withHeaders(['Destination' => $destination])->send('MKCOL', $upload);
            $this->requireStatus($response, [201, 405], 'Preparazione del caricamento non riuscita.');
        }

        $stream = fopen($path, 'rb');
        if (! $stream) {
            throw new RuntimeException('Blocco del file non leggibile.');
        }
        try {
            $response = $this->client()->withHeaders([
                'Destination' => $destination,
                'OC-Total-Length' => (string) $file->size,
            ])->withBody($stream, 'application/octet-stream')
                ->send('PUT', $upload.'/'.sprintf('%05d', $index));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $this->requireStatus($response, [201, 204], 'Invio del blocco non riuscito.');
    }

    public function finishFile(object $transfer, object $file): void
    {
        $destination = $this->filesUrl(config('airmail.folder').'/'.$transfer->id.'/'.$file->stored_name);
        $response = $this->client()->withHeaders([
            'Destination' => $destination,
            'OC-Total-Length' => (string) $file->size,
        ])->send('MOVE', $this->uploadsUrl($file->id).'/.file');

        if (! $response->successful()) {
            $existing = $this->client()->head($destination);
            $this->requireStatus($existing, [200], 'Completamento del file non riuscito.');
        }
    }

    public function openFile(object $transfer, object $file, ?string $range = null): Response
    {
        $request = $this->client()->withOptions(['stream' => true, 'timeout' => 0])
            ->withHeaders(['Accept-Encoding' => 'identity']);
        if ($range !== null) {
            $request = $request->withHeaders(['Range' => $range]);
        }
        return $request->get($this->filesUrl(config('airmail.folder').'/'.$transfer->id.'/'.$file->stored_name));
    }

    public function removeTransfer(object $transfer, array $fileIds): void
    {
        $response = $this->client()->delete($this->filesUrl(config('airmail.folder').'/'.$transfer->id));
        $this->requireStatus($response, [200, 204, 404], 'Rimozione dei file non riuscita.');

        foreach ($fileIds as $fileId) {
            $response = $this->client()->delete($this->uploadsUrl($fileId));
            $this->requireStatus($response, [200, 204, 404], 'Rimozione dei blocchi incompleti non riuscita.');
        }
    }

    private function createDirectory(string $url): void
    {
        $response = $this->client()->send('MKCOL', $url);
        $this->requireStatus($response, [201, 405], 'Creazione della cartella non riuscita.');
    }

    private function client(): PendingRequest
    {
        $encrypted = DB::table('airmail_settings')->value('encrypted_password');
        if (! $encrypted) {
            throw new RuntimeException('Configura Airmail nelle Impostazioni.');
        }

        return Http::withBasicAuth(config('airmail.username'), Crypt::decryptString($encrypted))
            ->connectTimeout(10)->timeout(180)
            ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false);
    }

    private function filesUrl(string $path = ''): string
    {
        $base = rtrim(config('airmail.base_url'), '/').'/remote.php/dav/files/'.rawurlencode(config('airmail.username'));
        return $path === '' ? $base.'/' : $base.'/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function uploadsUrl(string $id): string
    {
        return rtrim(config('airmail.base_url'), '/').'/remote.php/dav/uploads/'.rawurlencode(config('airmail.username')).'/'.$id;
    }

    private function requireStatus($response, array $allowed, string $message): void
    {
        if (! in_array($response->status(), $allowed, true)) {
            throw new RuntimeException($message.' (HTTP '.$response->status().')');
        }
    }
}
