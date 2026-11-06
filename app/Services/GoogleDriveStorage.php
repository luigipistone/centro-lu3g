<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoogleDriveStorage
{
    private const API = 'https://www.googleapis.com/drive/v3/files';

    public function root(): string
    {
        return (string) config('google-drive.shared_drive_id');
    }

    public function configured(): bool
    {
        return $this->root() !== '' && is_readable((string) config('google-drive.credentials'));
    }

    protected function token(): string
    {
        $path = (string) config('google-drive.credentials');
        if (! $this->configured()) throw new RuntimeException('Drive non configurato.');

        return Cache::remember('drive-access-'.hash('sha256', $path.filemtime($path)), 3000, function () use ($path) {
            $key = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $jwt = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
                'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600,
            ]));
            if (! openssl_sign($jwt, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Credenziale Drive non valida.');
            }
            $response = Http::timeout(20)->asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt.'.'.$encode($signature),
            ]);
            if (! $response->successful() || ! $response->json('access_token')) throw new RuntimeException('Accesso Drive non riuscito.');

            return $response->json('access_token');
        });
    }

    private function request()
    {
        return Http::withToken($this->token())->connectTimeout(10)->timeout(120);
    }

    public function metadata(string $id): array
    {
        $response = $this->request()->get(self::API.'/'.rawurlencode($id), [
            'supportsAllDrives' => 'true',
            'fields' => 'id,name,mimeType,driveId,trashed,capabilities(canAddChildren,canDownload)',
        ]);
        if (! $response->successful()) throw new RuntimeException('File o cartella Drive non disponibile.');
        $file = $response->json();
        if (($file['driveId'] ?? null) !== $this->root() || ($file['trashed'] ?? false)) {
            throw ValidationException::withMessages(['folder' => 'Seleziona una cartella del Drive aziendale.']);
        }

        return $file;
    }

    public function folder(string $id): array
    {
        $folder = $this->metadata($id);
        if (($folder['mimeType'] ?? '') !== 'application/vnd.google-apps.folder' || ! ($folder['capabilities']['canAddChildren'] ?? false)) {
            throw ValidationException::withMessages(['folder' => 'Questa cartella non consente il caricamento.']);
        }

        return $folder;
    }

    public function folders(string $parent, ?string $cursor): array
    {
        $folder = $this->folder($parent);
        $response = $this->request()->get(self::API, array_filter([
            'q' => "'".str_replace(["\\", "'"], ["\\\\", "\\'"], $parent)."' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'",
            'corpora' => 'drive', 'driveId' => $this->root(), 'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true', 'pageSize' => 100, 'pageToken' => $cursor,
            'orderBy' => 'name', 'fields' => 'files(id,name),nextPageToken',
        ], fn ($value) => $value !== null));
        if (! $response->successful()) throw new RuntimeException('Impossibile leggere le cartelle Drive.');

        return ['folder' => ['id' => $folder['id'], 'name' => $folder['name']], 'folders' => $response->json('files', []), 'cursor' => $response->json('nextPageToken')];
    }

    public function upload(UploadedFile $file, string $folder): array
    {
        $this->folder($folder);
        $response = $this->request()->withHeaders([
            'X-Upload-Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
            'X-Upload-Content-Length' => (string) $file->getSize(),
        ])->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true&fields=id,size', [
            'name' => $file->getClientOriginalName(), 'parents' => [$folder],
        ]);
        $location = $response->header('Location');
        if (! $response->successful() || parse_url($location ?? '', PHP_URL_HOST) !== 'www.googleapis.com' || parse_url($location ?? '', PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Impossibile avviare il caricamento su Drive.');
        }
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            $result = $this->request()->withBody($stream, $file->getMimeType() ?: 'application/octet-stream')->put($location);
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
        if (! $result->successful() || ! $result->json('id') || (int) $result->json('size', -1) !== $file->getSize()) {
            throw new RuntimeException('Caricamento Drive non completato. Verifica la cartella prima di riprovare.');
        }

        return $result->json();
    }

    public function download(string $id)
    {
        $file = $this->metadata($id);
        if (! ($file['capabilities']['canDownload'] ?? false)) throw new RuntimeException('Download non disponibile.');
        $response = $this->request()->withOptions(['stream' => true])->get(self::API.'/'.rawurlencode($id), ['alt' => 'media', 'supportsAllDrives' => 'true']);
        if (! $response->successful()) throw new RuntimeException('Download Drive non riuscito.');

        return $response->toPsrResponse()->getBody();
    }
}
