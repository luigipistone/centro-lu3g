<?php

namespace App\Services;

final class MailMessageContent
{
    public static function current(?string $body): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $body ?? ''));
        $result = [];
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/^\s*>/u', $line)) continue;

            // Require a complete reply attribution, including wrapped Gmail headers.
            $header = '';
            $headerEnd = null;
            for ($j = $i; $j < min(count($lines), $i + 5); $j++) {
                $header .= ' '.trim($lines[$j]);
                if (preg_match('/^\s*(?:On\s+.+\s+wrote|Il\s+.+\s+ha scritto)\s*:\s*$/iu', $header)) {
                    $headerEnd = $j;
                    break;
                }
            }
            if ($headerEnd !== null) {
                $next = $headerEnd + 1;
                while (isset($lines[$next]) && trim($lines[$next]) === '') $next++;
                // Quoted lines can contain interleaved new replies: do not discard those.
                if (isset($lines[$next]) && preg_match('/^\s*>/', $lines[$next])) {
                    $i = $headerEnd;
                    continue;
                }
                break;
            }
            if (preg_match('/^\s*-{2,}\s*(?:Original Message|Messaggio originale|Forwarded message|Messaggio inoltrato)\s*-{2,}\s*$/iu', $line)) break;
            if (preg_match('/^\s*(?:From|Da):\s*\S/iu', $line)) {
                $following = implode("\n", array_slice($lines, $i + 1, 8));
                if (preg_match('/^\s*(?:Sent|Inviato|Date|Data):/imu', $following)
                    && preg_match('/^\s*(?:Subject|Oggetto):/imu', $following)) break;
            }
            $result[] = $lines[$i];
        }
        return self::withoutFooter(implode("\n", $result));
    }

    private static function withoutFooter(string $text): string
    {
        // Remove only recognizable footer paragraphs, not arbitrary occurrences of legal words.
        $paragraphs = preg_split('/\n[\t ]*\n/', $text);
        $kept = [];
        foreach ($paragraphs as $paragraph) {
            $parts = preg_split('/(?=^\s*P\.?\s*S\.?\s*:)/imu', $paragraph);
            foreach ($parts as $part) {
                if (preg_match('/^\s*(?:(?:Questo messaggio(?: di posta elettronica)?|La presente (?:e-?mail|comunicazione)) (?:[eè] (?:strettamente )?riservat[oa]|contiene informazioni (?:riservate|confidenziali))|Le informazioni contenute (?:in questa|nella presente) [^\n]{0,80}sono (?:riservate|confidenziali)|This (?:e-?mail|message)(?: and (?:any |its )?attachments)? (?:is confidential|contains confidential|is intended only)|The information contained in this (?:e-?mail|message) is confidential|Please consider the environment before printing|Prima di stampare questa (?:e-?mail|mail)[^\n]*(?:ambiente|necessario)|Rispetta l.ambiente[^\n]*stampare)/iu', $part)) continue;
                $kept[] = $part;
            }
        }
        $lines = explode("\n", implode("\n\n", $kept));
        $result = [];
        $signature = false;
        foreach ($lines as $line) {
            $value = trim($line);
            if (preg_match('/^(?:Cordiali saluti|Distinti saluti|Un cordiale saluto|Cordialmente|Kind regards|Best regards|Regards|Sincerely|Grazie(?: mille)?|Thank you|Thanks)[,!\.\s]*$/iu', $value)
                || preg_match('/^--\s*$/', $line)) {
                $signature = true;
                continue;
            }
            if (preg_match('/^(?:Inviato da(?:l mio)? (?:iPhone|iPad|Android|Samsung)|Sent from my (?:iPhone|iPad|Android|Samsung)|Get Outlook for (?:iOS|Android)|Ottieni Outlook per (?:iOS|Android))/iu', $value)) continue;
            if ($signature) {
                if ($value === '' || self::signatureLine($value)) continue;
                // A postscript or unrecognized sentence may contain a request: retain it.
                $signature = false;
            }
            $result[] = $line;
        }
        return trim(preg_replace('/\n[\t ]*\n(?:[\t ]*\n)+/', "\n\n", implode("\n", $result)));
    }

    private static function signatureLine(string $line): bool
    {
        return (bool) preg_match('/^(?:[\p{Lu}][\p{L}\x{2019}\x{0027}-]+\s+){1,3}[\p{Lu}][\p{L}\x{2019}\x{0027}-]+$/u', $line)
            || (bool) preg_match('/^(?:Tel(?:efono)?|Cell(?:ulare)?|Mobile|Fax|E-?mail|Web|Sito web|P\.?\s*IVA)\s*[:.].*$/iu', $line)
            || (bool) preg_match('/^(?:https?:\/\/|www\.)\S+$/iu', $line)
            || (bool) preg_match('/^[\p{L}\p{N} &.\x{0027}-]{1,70}\s+(?:S\.?R\.?L\.?|S\.?P\.?A\.?|Ltd\.?|LLC)$/iu', $line)
            || filter_var($line, FILTER_VALIDATE_EMAIL) !== false
            || (bool) preg_match('/^(?:CEO|CTO|Founder|Co-founder|Managing Director|Account Manager|Project Manager|Responsabile commerciale|Amministratore delegato)$/iu', $line);
    }
}
