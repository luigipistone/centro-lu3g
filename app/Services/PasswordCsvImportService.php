<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordCsvImportService
{
    public function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Impossibile leggere il CSV.']);
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages(['file' => 'Il CSV è vuoto.']);
            }
            $delimiter = collect([',', ';', "\t"])
                ->sortByDesc(fn ($separator) => count(str_getcsv($firstLine, $separator)))
                ->first();
            rewind($handle);
            $headers = array_map(fn ($value) => $this->header((string) $value), fgetcsv($handle, 0, $delimiter) ?: []);
            $columns = [
                'title' => $this->column($headers, ['titolo', 'title', 'nome', 'name']),
                'username' => $this->column($headers, ['nome utente', 'username', 'user name', 'utente', 'login']),
                'password' => $this->column($headers, ['password', 'psw', 'pass', 'pwd']),
                'url' => $this->column($headers, ['url', 'sito web', 'website', 'link']),
                'notes' => $this->column($headers, ['note', 'notes']),
            ];
            if ($columns['title'] === null || $columns['username'] === null || $columns['password'] === null) {
                throw ValidationException::withMessages(['file' => 'Servono le colonne Titolo, Nome utente e Password.']);
            }

            $records = [];
            $skipped = 0;
            $line = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($line > 5001) {
                    throw ValidationException::withMessages(['file' => 'Il CSV supera il limite di 5.000 righe.']);
                }
                $title = trim((string) ($row[$columns['title']] ?? ''));
                $username = trim((string) ($row[$columns['username']] ?? ''));
                $password = (string) ($row[$columns['password']] ?? '');
                $url = $columns['url'] === null ? '' : trim((string) ($row[$columns['url']] ?? ''));
                $notes = $columns['notes'] === null ? '' : trim((string) ($row[$columns['notes']] ?? ''));
                if ($title === '' || $password === '' || mb_strlen($title) > 255 || mb_strlen($username) > 255
                    || mb_strlen($password) > 2000 || mb_strlen($url) > 1000 || mb_strlen($notes) > 10000) {
                    $skipped++;
                    continue;
                }
                $records[] = [
                    'title' => Str::title(Str::lower($title)),
                    'username' => $username,
                    'password' => $password,
                    'url' => $url,
                    'notes' => $notes,
                ];
            }

            if ($records === []) {
                throw ValidationException::withMessages(['file' => 'Nessuna credenziale valida nel CSV.']);
            }

            return [$records, $skipped];
        } finally {
            fclose($handle);
        }
    }

    public function titleKey(string $title): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($title)));
    }

    private function header(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s*\([^)]*\)$/u', '', ltrim($value, "\xEF\xBB\xBF"))));
    }

    private function column(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $index = array_search($name, $headers, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }
}
