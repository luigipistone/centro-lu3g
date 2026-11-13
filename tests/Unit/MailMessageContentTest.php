<?php

namespace Tests\Unit;

use App\Services\MailMessageContent;
use PHPUnit\Framework\TestCase;

class MailMessageContentTest extends TestCase
{
    private function graphicSignature(): string
    {
        return "[image: LU3G] <https://www.lu3g.it>\nè direzione.\nLU3G\nVia E. Fermi, 20\n20057 Assago (MI)\n<https://www.google.com/maps?daddr=Assago>\nPI: 12345678901\nt. +02 1234567 <+39021234567>\nwww.lu3g.it\n\n[image: Mario Rossi]\nMario Rossi\nPhotographer & Videomaker\nmario@example.com\nm. +39123456789\n[image: Facebook] <https://www.facebook.com/example> [image: Instagram]\n<https://www.instagram.com/example/> [image: LinkedIn]\n<https://www.linkedin.com/company/example>";
    }

    public function test_graphic_signature_without_salutation_preserves_request_and_delivery_link(): void
    {
        $request = "Cambiare transizione al secondo 40.\nTesto esatto: La formazione è nel nostro DNA\nhttps://www.transfernow.net/dl/example";
        $clean = MailMessageContent::current($request."\n\n".$this->graphicSignature()."\n\nP.S.: Non allungare il video.");
        $this->assertSame($request."\n\nP.S.: Non allungare il video.", $clean);
        $this->assertSame($clean, MailMessageContent::current($clean));
    }

    public function test_company_signature_and_social_wrapping(): void
    {
        $this->assertSame('Cambiare il logo del cartello.', MailMessageContent::current("Cambiare il logo del cartello.\n\nElena\n[image: Logo]\nEXAMPLE SRL\nVia degli Abeti 17/5\nTel: 123456\nWeb: visita il nostro sito <https://example.com/> |\nSocial: Facebook <https://facebook.com/example> ·\nInstagram\n<https://instagram.com/example> ·\nLinkedIn <https://linkedin.com/example>"));
    }

    public function test_keeps_contact_copy_image_references_and_download_after_signature(): void
    {
        $copy = "Sostituisci i contatti con questi:\nMario Rossi\nAccount Manager\nVia Roma 1\nTel: 123456\nmario@example.com";
        $this->assertSame($copy, MailMessageContent::current($copy));
        $this->assertSame("Modifica questa immagine:\n[cid:example]\nUsa il logo nuovo.", MailMessageContent::current("Modifica questa immagine:\n[cid:example]\nUsa il logo nuovo."));
        $this->assertStringContainsString('https://www.swisstransfer.com/dl/example', MailMessageContent::current("Richiesta.\n\n".$this->graphicSignature()."\n\nhttps://www.swisstransfer.com/dl/example"));
    }

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
