<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

final class TextSearch
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower(Str::ascii($value ?? '')));
    }

    public static function apply(Builder $query, array $columns, string $search): void
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower(Str::ascii($search)), $matches);
        if (! $matches[0]) return;

        $connection = $query->getConnection();
        $sqlite = $connection->getDriverName() === 'sqlite';
        if ($sqlite) {
            $connection->getPdo()->sqliteCreateFunction('centro_search_text', self::normalize(...), 1);
        }
        $fields = array_map(fn ($column) => 'COALESCE('.$query->getGrammar()->wrap($column).", '')", $columns);
        $text = $sqlite ? implode(" || ' ' || ", $fields) : "CONCAT_WS(' ', ".implode(', ', $fields).')';
        if ($sqlite) {
            $text = 'centro_search_text('.$text.')';
        } else {
            // Accent-insensitive comparison, with punctuation removed before pagination.
            $text = "LOWER(CONVERT($text USING utf8mb4))";
            foreach (['ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'ł' => 'l'] as $from => $to) {
                $text = "REPLACE($text, '$from', '$to')";
            }
            $text = "REGEXP_REPLACE($text, '[^[:alnum:]]', '') COLLATE utf8mb4_unicode_ci";
        }
        foreach ($matches[0] as $token) {
            $query->whereRaw("$text LIKE ?", ['%'.$token.'%']);
        }
    }
}
