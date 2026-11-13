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
        return trim(preg_replace('/\n[\t ]*\n(?:[\t ]*\n)+/', "\n\n", implode("\n", $result)));
    }
}
