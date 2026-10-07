<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class PayslipRecognitionService
{
    private const MONTHS = [
        'GENNAIO', 'FEBBRAIO', 'MARZO', 'APRILE', 'MAGGIO', 'GIUGNO',
        'LUGLIO', 'AGOSTO', 'SETTEMBRE', 'OTTOBRE', 'NOVEMBRE', 'DICEMBRE',
    ];

    public function recognize(string $path): ?array
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (\Throwable) {
            return null;
        }

        return $this->recognizeText($text);
    }

    public function recognizeText(string $text): ?array
    {
        $period = implode('|', self::MONTHS);
        if (! preg_match('/('.$period.')\s*(20\d{2})/iu', $text, $periodMatch)) {
            return null;
        }

        // The employee row follows "Codice Dip. Cognome Nome Codice fiscale" in this payslip layout.
        if (! preg_match('/\b\d{4,8}\s+([A-ZÀ-Ý][A-ZÀ-Ý\s\'\-]{2,80}?)\s+([A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z])\b/iu', $text, $personMatch)) {
            return null;
        }

        $month = array_search(mb_strtoupper($periodMatch[1]), self::MONTHS, true) + 1;
        $year = (int) $periodMatch[2];

        return [
            'name' => trim(preg_replace('/\s+/u', ' ', $personMatch[1])),
            'fiscal_code' => strtoupper($personMatch[2]),
            'month' => $month,
            'year' => $year,
            'title' => 'Compenso '.ucfirst(mb_strtolower(self::MONTHS[$month - 1])).' '.$year,
        ];
    }
}
