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
        $text = self::withoutSignatureBlocks($text);
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

    private static function withoutSignatureBlocks(string $text): string
    {
        $lines = explode("\n", $text);
        for ($i = 0; $i < count($lines); $i++) {
            $first = self::footerKind(trim($lines[$i]));
            if (!in_array($first, ['identity', 'image', 'address'], true)) continue;
            $previous = $i - 1;
            while ($previous >= 0 && trim($lines[$previous]) === '') $previous--;
            if ($previous >= 0 && str_ends_with(trim($lines[$previous]), ':')) {
                // Contact details explicitly introduced by the message can be requested copy.
                while ($i + 1 < count($lines) && self::footerKind(trim($lines[$i + 1])) !== null) $i++;
                continue;
            }
            $kinds = [];
            $end = $i;
            while ($end < count($lines) && ($kind = self::footerKind(trim($lines[$end]))) !== null) {
                $kinds[$kind] = true;
                $end++;
            }
            // A logo/name alone is not a signature: require independent contact evidence.
            $contacts = count(array_intersect(array_keys($kinds), ['phone', 'email', 'web', 'tax']));
            if ($contacts < 2 || (!isset($kinds['address']) && !isset($kinds['role']))) continue;
            for ($j = $i; $j < $end; $j++) $lines[$j] = '';
            $i = $end - 1;
        }
        return implode("\n", $lines);
    }

    private static function footerKind(string $line): ?string
    {
        if ($line === '' || preg_match('/^[_-]{3,}$/', $line)) return 'blank';
        if (preg_match('/^(?:\[(?:image:|cid:)[^\]]+\]\s*(?:<https?:\/\/[^>]+>\s*)?)+$/iu', $line)) return 'image';
        if (preg_match('/^(?:\[Interfaz de usuario gráfica, Aplicación|LEGAL\s*\/\s*DISCLAIMER|è direzione\.)/iu', $line)) return 'boilerplate';
        if (preg_match('/^(?:\+\s*\d[\d\s().-]{6,}|(?:t|m|tel|telefono|cell|mobile|fax)[.:]\s*\+?\d.*)$/iu', $line)) return 'phone';
        if (filter_var($line, FILTER_VALIDATE_EMAIL)) return 'email';
        if (preg_match('/^(?:PI|P\.?\s*IVA)\s*:\s*[\d ]+$/iu', $line)) return 'tax';
        if (preg_match('~https?://(?:[^/]+\.)?(?:transfernow\.net|swisstransfer\.com|wetransfer\.com|drive\.google\.com|dropbox\.com)/~iu', $line)) return null;
        if (preg_match('/^(?:<?https?:\/\/|www\.)\S+(?:\s*\[image:[^\]]+\])*(?:\s*[·|])?$/iu', $line)
            || preg_match('/^(?:Web: visita il nostro sito|Social: Facebook|LinkedIn\s*<|Instagram\s*$)/iu', $line)) return 'web';
        if (preg_match('/^(?:(?:Via|Viale|Piazza|Corso|Palazzo)\s+[^\n]{1,100}\d[^\n]*|\d{5}\s+[\p{L}\s().-]+|[\p{L} ]+\s+Strada\s+\d+,?)$/iu', $line)) return 'address';
        if (preg_match('/^(?:(?:Senior |Junior )?(?:Brand |Account |Project |Marketing & Communication )?Manager(?: MHC)?|Photographer & Videomaker|CEO(?: & Business Developer)?|Amministratore|Responsabile commerciale)$/iu', $line)) return 'role';
        if (preg_match('/^(?:[\p{Lu}][\p{L}\x{2019}\x{0027}-]+)(?:\s+[\p{Lu}][\p{L}\x{2019}\x{0027}-]+){0,3}$/u', $line)
            || preg_match('/^[\p{L}\p{N} &.\x{0027}-]{1,70}\s+(?:S\.?R\.?L\.?|S\.?P\.?A\.?|Ltd\.?|LLC)$/iu', $line)
            || $line === 'LU3G') return 'identity';
        return null;
    }
}
