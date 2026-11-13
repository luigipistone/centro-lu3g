<?php

namespace Tests\Unit;

use App\Services\MailMessageContent;
use PHPUnit\Framework\TestCase;

class MailMessageContentTest extends TestCase
{
    public function test_removes_signature_and_disclaimer_but_preserves_postscript(): void
    {
        $body = "Aggiornare il logo.\n\nCordiali saluti,\nMario Rossi\nAccount Manager\nTel: 123456\nwww.example.com\nmario@example.com\n\nQuesto messaggio contiene informazioni riservate.\nNon divulgarlo.\nP.S.: Aggiornare anche il footer.";
        $clean = MailMessageContent::current($body);
        $this->assertSame("Aggiornare il logo.\n\nP.S.: Aggiornare anche il footer.", $clean);
        $this->assertSame($clean, MailMessageContent::current($clean));
    }

    public function test_preserves_instructions_and_contact_details_outside_signature(): void
    {
        $body = "Questo messaggio contiene le modifiche richieste.\nTel: 123456\nwww.example.com\n\nModificare la privacy policy e il disclaimer.\nGrazie, aggiornate anche il menu.";
        $this->assertSame($body, MailMessageContent::current($body));
        $this->assertSame("Aggiornare logo\nAggiornare anche il menu", MailMessageContent::current("Aggiornare logo\nGrazie\nMario Rossi\nAggiornare anche il menu"));
    }

    public function test_english_signature_mobile_footer_and_empty_content(): void
    {
        $this->assertSame('Update logo', MailMessageContent::current("Update logo\nBest regards,\nJane Smith\nCEO\n\nThis email is confidential.\nDo not disclose.\n\nSent from my iPhone"));
        $this->assertSame('', MailMessageContent::current("Cordiali saluti\nMario Rossi"));
        $this->assertSame('Nuovo', MailMessageContent::current("Nuovo\nInviato dal mio iPhone"));
    }

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
