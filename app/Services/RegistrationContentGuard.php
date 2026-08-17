<?php

namespace App\Services;

use App\Models\User;

/**
 * Content-based registration fraud heuristics: gibberish names, dot-farmed
 * Gmail aliases, disposable email domains, and honeypot detection. Pure
 * scoring logic — no DB writes, only a read for the Gmail-dedup check.
 *
 * Sits alongside RegistrationSecurityService's IP/phone/KYC checks, which
 * catch a different class of abuse (same actor, many accounts) than this
 * service (single obviously-fake account, e.g. bot-generated names).
 */
class RegistrationContentGuard
{
    /**
     * @return array{score: int, reasons: array<string>, hard_block: bool}
     */
    public function evaluate(string $firstName, string $lastName, string $email, ?string $honeypot = null): array
    {
        if ($honeypot !== null && trim($honeypot) !== '') {
            return ['score' => 100, 'reasons' => ['honeypot_filled'], 'hard_block' => true];
        }

        $score = 0;
        $reasons = [];

        foreach (['first_name' => $firstName, 'last_name' => $lastName] as $label => $name) {
            if ($this->looksLikeGibberish($name)) {
                $score += 30;
                $reasons[] = "{$label}_gibberish";
            }
        }

        $emailLocalPart = strstr($email, '@', true) ?: $email;
        if ($this->looksLikeGibberish($emailLocalPart)) {
            $score += 20;
            $reasons[] = 'email_local_part_gibberish';
        }

        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        if ($domain !== '' && in_array($domain, config('u9itus.security.disposable_email_domains', []), true)) {
            $score += 80;
            $reasons[] = 'disposable_email_domain';
        }

        if ($domain !== '' && ! $this->domainHasMailExchanger($domain)) {
            $score += 40;
            $reasons[] = 'email_domain_no_mx_record';
        }

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true) && $this->isDotFarmedGmailDuplicate($email)) {
            $score += 35;
            $reasons[] = 'dot_farmed_gmail_duplicate';
        }

        return [
            'score' => $score,
            'reasons' => $reasons,
            'hard_block' => false,
        ];
    }

    /**
     * Flags strings that look randomly generated rather than a plausible
     * human name/local-part: no vowels, a long run of consonants, or
     * excessive mid-token case-switching (e.g. "tcBmySdXtrjuWJsH").
     */
    private function looksLikeGibberish(string $value): bool
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $value) ?? '';
        if (strlen($letters) < 5) {
            return false;
        }

        if (! preg_match('/[aeiouAEIOU]/', $letters)) {
            return true;
        }

        if (preg_match('/[^aeiouAEIOU]{5,}/', $letters)) {
            return true;
        }

        $caseSwitches = 0;
        $length = strlen($letters);
        for ($i = 1; $i < $length; $i++) {
            $prevIsUpper = ctype_upper($letters[$i - 1]);
            $currIsUpper = ctype_upper($letters[$i]);
            if ($prevIsUpper !== $currIsUpper) {
                $caseSwitches++;
            }
        }

        return $length >= 8 && $caseSwitches >= (int) ceil($length * 0.4);
    }

    /**
     * Gmail ignores dots and anything after a '+' in the local part, so
     * "b.esf.or.dhv@gmail.com" and "besfordhv@gmail.com" are the same inbox.
     * Bots exploit this to farm many "unique" addresses off one real inbox.
     */
    private function isDotFarmedGmailDuplicate(string $email): bool
    {
        $normalized = $this->normalizeGmail($email);

        return User::where('email', '!=', $email)
            ->where(function ($query) {
                $query->where('email', 'like', '%@gmail.com')
                    ->orWhere('email', 'like', '%@googlemail.com');
            })
            ->pluck('email')
            ->contains(fn (string $existing) => $this->normalizeGmail($existing) === $normalized);
    }

    private function normalizeGmail(string $email): string
    {
        $local = strstr($email, '@', true) ?: $email;
        $local = strstr($local, '+', true) ?: $local;
        $local = str_replace('.', '', $local);

        return strtolower($local) . '@gmail.com';
    }

    private function domainHasMailExchanger(string $domain): bool
    {
        if (app()->runningUnitTests()) {
            // Avoid real DNS lookups in tests; disposable-domain/gibberish
            // checks above already exercise the blocking behavior.
            return true;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }
}
