<?php

namespace Tests\Unit;

use App\Services\MailMessageContent;
use PHPUnit\Framework\TestCase;

class MailMessageContentTest extends TestCase
{
    public function test_removes_italian_and_english_history(): void
    {
        foreach (["Il 12 novembre Mario ha scritto:", "On Thu, Nov 12, Mario wrote:"] as $header) {
            $this->assertSame('Nuova modifica', MailMessageContent::current("Nuova modifica\n\n$header\nVecchia richiesta\nAltra richiesta"));
        }
    }

    public function test_preserves_inline_answers_after_wrapped_attribution(): void
    {
        $body = "Nuovo testo\nOn Thu, Nov 12, Mario\n<mario@example.com> wrote:\n> Cambiare logo?\nSi, usa il nuovo.\n> Cambiare colore?\nNo, lascialo.";
        $this->assertSame("Nuovo testo\nSi, usa il nuovo.\nNo, lascialo.", MailMessageContent::current($body));
    }

    public function test_outlook_and_original_message(): void
    {
        $this->assertSame('Nuovo', MailMessageContent::current("Nuovo\nDa: Mario\nInviato: ieri\nA: Luca\nOggetto: Vecchio\nVecchio testo"));
        $this->assertSame('Nuovo', MailMessageContent::current("Nuovo\n-----Original Message-----\nVecchio"));
    }

    public function test_preserves_operational_content_and_is_idempotent(): void
    {
        $body = "Da: Milano\nA: Roma\nOggetto: nuova pagina\n- Cambia logo\n- Usa https://example.com\nTesto esatto: ha scritto: ciao";
        $this->assertSame($body, MailMessageContent::current($body));
        $this->assertSame($body, MailMessageContent::current(MailMessageContent::current($body)));
        $this->assertSame('', MailMessageContent::current("> Solo cronologia\n> Nessuna nuova richiesta"));
    }
}
