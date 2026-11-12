<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MailClientMatcher
{
    private const GENERIC = ['gmail.com', 'google.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'yahoo.it', 'icloud.com', 'libero.it', 'virgilio.it', 'alice.it', 'tiscali.it', 'facebook.com', 'instagram.com', 'linkedin.com', 'youtube.com', 'youtu.be', 'bit.ly', 't.co', 'wetransfer.com', 'whatsapp.com'];

    private function domain(string $value): ?string
    {
        $host = strtolower((string) parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST));
        $host = rtrim(preg_replace('/^www\./', '', $host), '.');
        if ($host === '') return null;
        if (function_exists('idn_to_ascii')) $host = idn_to_ascii($host) ?: $host;
        if (! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) return null;
        foreach (self::GENERIC as $generic) if ($host === $generic || str_ends_with($host, '.'.$generic)) return null;
        return $host;
    }

    public function match($messages, $sites): array
    {
        $clients = DB::table('clients')->where('active', true)->get(['id', 'name', 'email', 'website']);
        $contacts = DB::table('client_contacts')->get(['client_id', 'email']);
        $internal = DB::table('users')->pluck('email')->map(fn ($e) => strtolower(trim($e)))->all();
        $senders = collect($messages)->pluck('sender')->map(fn ($e) => strtolower(trim($e)))->filter(fn ($e) => $e && ! in_array($e, $internal, true))->unique();
        $tiers = [[], [], []];
        $domains = $senders->map(fn ($e) => $this->domain(substr(strrchr($e, '@') ?: '', 1)))->filter()->all();
        $bodyDomains = [];
        foreach ($messages as $m) {
            preg_match_all('~(?:https?://|www\.)[^\s<>"\x27]+~iu', $m->body, $urls);
            foreach ($urls[0] as $url) if ($host = $this->domain($url)) $bodyDomains[] = $host;
        }
        foreach ($clients as $c) {
            $emails = $contacts->where('client_id', $c->id)->pluck('email')->push($c->email)->filter()->map(fn ($e) => strtolower(trim($e)));
            if ($emails->intersect($senders)->isNotEmpty()) $tiers[0][$c->id] = ['id' => $c->id, 'name' => $c->name, 'reason' => 'Email del cliente o referente'];
            $known = $emails->map(fn ($e) => $this->domain(substr(strrchr($e, '@') ?: '', 1)))->filter()->all();
            if ($host = $this->domain($c->website ?? '')) $known[] = $host;
            $web = collect($sites)->where('client_id', $c->id)->pluck('url')->push($c->website)->filter()->map(fn ($url) => $this->domain($url))->filter()->all();
            if (array_intersect($domains, array_merge($known, $web))) $tiers[1][$c->id] = ['id' => $c->id, 'name' => $c->name, 'reason' => 'Dominio del mittente'];
            if (array_intersect($bodyDomains, $web)) $tiers[2][$c->id] = ['id' => $c->id, 'name' => $c->name, 'reason' => 'Sito citato nel messaggio'];
        }
        foreach ($tiers as $tier) {
            if ($tier) return ['client_id' => count($tier) === 1 ? array_key_first($tier) : null, 'candidates' => array_values($tier)];
        }
        return ['client_id' => null, 'candidates' => []];
    }
}
