<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientCsvImportService
{
    public const COLUMNS = [
        'name' => 'Nome', 'legal_name' => 'Ragione sociale', 'email' => 'Email',
        'phone' => 'Telefono', 'vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale',
        'business_sector' => 'Settore',
        'country' => 'Paese', 'street' => 'Via',
        'street_number' => 'Numero', 'postal_code' => 'CAP', 'city' => 'Citta',
        'province' => 'Provincia', 'website' => 'Sito web', 'notes' => 'Note',
    ];

    public function preview(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'Impossibile leggere il CSV.']);
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages(['file' => 'Il CSV è vuoto.']);
            }
            $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($separator) => count(str_getcsv($firstLine, $separator)))->first();
            rewind($handle);
            $headers = array_map(fn ($value) => $this->normalize((string) $value), fgetcsv($handle, 0, $delimiter) ?: []);
            $indexes = [];
            foreach (self::COLUMNS as $key => $label) {
                $index = array_search($this->normalize($label), $headers, true);
                if ($index === false) $index = array_search($this->normalize($key), $headers, true);
                if ($index !== false) $indexes[$key] = $index;
            }
            if (! isset($indexes['name'])) {
                throw ValidationException::withMessages(['file' => 'Il CSV deve contenere la colonna Nome.']);
            }

            $existing = DB::table('clients')->get(['name', 'email', 'vat_number', 'tax_code']);
            $records = [];
            $issues = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($line > 2001) {
                    throw ValidationException::withMessages(['file' => 'Il CSV supera il limite di 2.000 righe.']);
                }
                if (count($row) === 1 && trim((string) $row[0]) === '') continue;
                $record = [];
                foreach ($indexes as $key => $index) {
                    $value = trim((string) ($row[$index] ?? ''));
                    $record[$key] = $value === '' ? null : $value;
                }
                if (! filled($record['name'] ?? null)) {
                    $issues[] = ['line' => $line, 'reason' => 'Nome mancante'];
                    continue;
                }
                $rules = [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'notes' => ['nullable', 'string', 'max:10000'],
                ];
                foreach (array_diff(array_keys(self::COLUMNS), ['name', 'email', 'notes']) as $key) {
                    $rules[$key] = ['nullable', 'string', 'max:255'];
                }
                $validator = validator($record, $rules);
                if ($validator->fails()) {
                    $issues[] = ['line' => $line, 'reason' => 'Dati non validi: '.implode(', ', array_keys($validator->errors()->messages()))];
                    continue;
                }
                if ($existing->contains(fn ($client) => $this->matches($record, $client))
                    || collect($records)->contains(fn ($item) => $this->matches($record, (object) $item['data']))) {
                    $issues[] = ['line' => $line, 'reason' => 'Cliente già presente nel file o nel portale'];
                    continue;
                }
                $records[] = ['line' => $line, 'data' => $record];
            }

            return ['records' => $records, 'issues' => $issues];
        } finally {
            fclose($handle);
        }
    }

    private function matches(array $record, object $client): bool
    {
        foreach (['vat_number', 'tax_code'] as $key) {
            if (filled($record[$key] ?? null) && filled($client->{$key} ?? null) && $this->normalize($record[$key]) === $this->normalize($client->{$key})) return true;
        }
        return $this->normalize($record['name']) === $this->normalize($client->name)
            && ($this->normalize($record['email'] ?? '') === $this->normalize($client->email ?? '') || blank($record['email'] ?? null));
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', ltrim($value, "\xEF\xBB\xBF"))));
    }
}
