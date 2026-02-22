<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailDiagnostic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:diagnose
                            {--to= : Email address to send the test email to}
                            {--send : Actually attempt to send a test email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose email configuration and optionally send a test email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>U9itus — Email Diagnostic Tool</>');
        $this->line('  ' . str_repeat('─', 45));
        $this->newLine();

        $mailer     = config('mail.default');
        $fromAddr   = config('mail.from.address');
        $fromName   = config('mail.from.name');
        $appEnv     = config('app.env');
        $appUrl     = config('app.url');

        // ── 1. Environment summary ──────────────────────────────────────────
        $this->section('Environment');
        $this->infoRow('APP_ENV',    $appEnv);
        $this->infoRow('APP_URL',    $appUrl);
        $this->infoRow('MAIL_MAILER', $mailer);
        $this->infoRow('FROM',       "{$fromName} <{$fromAddr}>");

        // ── 2. Mailer-specific checks ───────────────────────────────────────
        $this->newLine();
        $this->section('Mail Transport Check');

        $issues = [];

        switch ($mailer) {

            case 'log':
                $this->warn('  ⚠  MAIL_MAILER=log — emails are written to storage/logs only, NOT delivered.');
                $issues[] = 'Switch MAIL_MAILER to a real transport (mailgun, smtp, ses…) for emails to be delivered.';
                break;

            case 'array':
                $this->warn('  ⚠  MAIL_MAILER=array — emails are captured in memory only, used for testing.');
                $issues[] = 'Switch MAIL_MAILER to a real transport for production/staging.';
                break;

            case 'mailgun':
                $domain  = config('services.mailgun.domain');
                $secret  = config('services.mailgun.secret');
                $endpoint = config('services.mailgun.endpoint', 'api.mailgun.net');

                $this->infoRow('MAILGUN_DOMAIN',   $domain  ?: '<fg=red>NOT SET</>');
                $this->infoRow('MAILGUN_SECRET',   $secret  ? $this->maskSecret($secret) : '<fg=red>NOT SET</>');
                $this->infoRow('MAILGUN_ENDPOINT', $endpoint);

                if (empty($domain)) {
                    $issues[] = 'MAILGUN_DOMAIN is not set.';
                }
                if (empty($secret)) {
                    $issues[] = 'MAILGUN_SECRET (API key) is not set.';
                }

                // Sandbox detection — the #1 reason emails fail
                if ($domain && str_contains($domain, 'sandbox')) {
                    $this->newLine();
                    $this->error('  ✖  SANDBOX DOMAIN DETECTED: ' . $domain);
                    $this->line('');
                    $this->line('  <fg=yellow>Mailgun sandbox domains can only deliver to</>');
                    $this->line('  <fg=yellow>explicitly authorized recipient addresses.</>');
                    $this->line('');
                    $this->line('  Fix options:');
                    $this->line('  <fg=green>  1. Add recipient in Mailgun dashboard:</>');
                    $this->line('       https://app.mailgun.com/mg/sending/domains/' . $domain . '/authorized-recipients');
                    $this->line('  <fg=green>  2. Use a real Mailgun domain (requires DNS setup).</>');
                    $this->line('  <fg=green>  3. Switch to a different mailer during development:</>');
                    $this->line('       MAIL_MAILER=log  (logs to storage/logs/laravel.log)');

                    $issues[] = 'Mailgun sandbox domain — only authorized recipients can receive mail.';
                } else {
                    $this->line('  <fg=green>✔</> Domain is not a sandbox.');
                }
                break;

            case 'smtp':
                $host = config('mail.mailers.smtp.host');
                $port = config('mail.mailers.smtp.port');
                $user = config('mail.mailers.smtp.username');
                $pass = config('mail.mailers.smtp.password');

                $this->infoRow('MAIL_HOST',     $host ?: '<fg=red>NOT SET</>');
                $this->infoRow('MAIL_PORT',     $port ?: '<fg=red>NOT SET</>');
                $this->infoRow('MAIL_USERNAME', $user ?: '<fg=yellow>empty</>');
                $this->infoRow('MAIL_PASSWORD', $pass ? $this->maskSecret($pass) : '<fg=yellow>empty</>');

                if (empty($host)) {
                    $issues[] = 'MAIL_HOST is not configured.';
                }
                if (empty($user) || empty($pass)) {
                    $issues[] = 'MAIL_USERNAME / MAIL_PASSWORD are empty — authentication may fail.';
                }
                break;

            case 'ses':
                $key    = config('services.ses.key');
                $secret = config('services.ses.secret');
                $region = config('services.ses.region');

                $this->infoRow('AWS_ACCESS_KEY_ID',     $key    ? $this->maskSecret($key)    : '<fg=red>NOT SET</>');
                $this->infoRow('AWS_SECRET_ACCESS_KEY', $secret ? $this->maskSecret($secret) : '<fg=red>NOT SET</>');
                $this->infoRow('AWS_DEFAULT_REGION',    $region ?: '<fg=red>NOT SET</>');

                if (empty($key) || empty($secret)) {
                    $issues[] = 'AWS credentials are missing for SES.';
                }
                break;

            default:
                $this->warn("  Unknown mailer: {$mailer}");
                $issues[] = "Unrecognised MAIL_MAILER value: {$mailer}";
        }

        // ── 3. FROM address check ───────────────────────────────────────────
        $this->newLine();
        $this->section('From Address Check');

        if (empty($fromAddr) || $fromAddr === 'hello@example.com') {
            $this->warn('  ⚠  MAIL_FROM_ADDRESS is not configured (still using placeholder).');
            $issues[] = 'Set MAIL_FROM_ADDRESS to a real address on your sending domain.';
        } else {
            $this->line('  <fg=green>✔</> FROM: ' . $fromAddr);
        }

        if ($mailer === 'mailgun') {
            $domain = config('services.mailgun.domain');
            if ($domain && !str_contains($fromAddr, '@' . $domain)) {
                $this->warn("  ⚠  FROM address domain doesn't match MAILGUN_DOMAIN ({$domain}).");
                $issues[] = "Set MAIL_FROM_ADDRESS to use @{$domain} or your verified sending domain.";
            }
        }

        // ── 4. Queue driver check ───────────────────────────────────────────
        $this->newLine();
        $this->section('Queue Check');
        $queueDriver = config('queue.default');
        $this->infoRow('QUEUE_CONNECTION', $queueDriver);

        if ($queueDriver === 'sync') {
            $this->line('  <fg=green>✔</> Queue is synchronous — mails send immediately.');
        } else {
            $this->line("  <fg=yellow>🕐  Using async queue ({$queueDriver}) — a queue worker must be running</>");
            $this->line('     for queued mails to be dispatched: <fg=cyan>php artisan queue:work</>');
        }

        // ── 5. Issue summary ────────────────────────────────────────────────
        $this->newLine();

        if (empty($issues)) {
            $this->line('  <fg=green;options=bold>✔ No configuration issues found.</>');
        } else {
            $this->section('Issues Found (' . count($issues) . ')');
            foreach ($issues as $i => $issue) {
                $this->line('  <fg=red>[' . ($i + 1) . ']</> ' . $issue);
            }
        }

        // ── 6. Optional test send ───────────────────────────────────────────
        if ($this->option('send')) {
            $to = $this->option('to') ?: $this->ask('Send test email to');

            if (empty($to)) {
                $this->error('No recipient provided. Use --to=you@example.com');
                return self::FAILURE;
            }

            $this->newLine();
            $this->section('Sending Test Email');
            $this->line("  → Sending to: <fg=cyan>{$to}</>");

            try {
                Mail::raw(
                    "This is a test email from U9itus.\n\nSent at: " . now()->toDateTimeString() . "\nMailer: {$mailer}",
                    function ($message) use ($to) {
                        $message->to($to)
                            ->subject('[U9itus] Email Diagnostic Test – ' . now()->toDateTimeString());
                    }
                );

                $this->line('  <fg=green;options=bold>✔ Test email sent successfully!</>');

            } catch (TransportExceptionInterface $e) {
                $this->error('  ✖  Transport error: ' . $e->getMessage());
                $this->newLine();
                $this->renderTransportTip($e->getMessage());
                return self::FAILURE;

            } catch (\Exception $e) {
                $this->error('  ✖  ' . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            $this->newLine();
            $this->line('  <fg=gray>Tip: Run with --send --to=you@example.com to send a test email.</>');
        }

        $this->newLine();
        return empty($issues) ? self::SUCCESS : self::FAILURE;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function section(string $title): void
    {
        $this->line("  <fg=cyan;options=bold>{$title}</>");
        $this->line('  ' . str_repeat('─', 40));
    }

    private function infoRow(string $label, mixed $value): void
    {
        $this->line(sprintf('  <fg=gray>%-26s</> %s', $label, $value));
    }

    private function maskSecret(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return str_repeat('*', strlen($secret));
        }
        return substr($secret, 0, 4) . str_repeat('*', strlen($secret) - 8) . substr($secret, -4);
    }

    private function renderTransportTip(string $message): void
    {
        if (str_contains($message, 'Sandbox subdomains are for test purposes only')) {
            $domain = config('services.mailgun.domain');
            $this->line('  <fg=yellow>HOW TO FIX — Mailgun Sandbox Restriction:</>');
            $this->line('');
            $this->line('  Option A — Add this address as an authorized recipient:');
            $this->line('  <fg=cyan>  https://app.mailgun.com/mg/sending/domains/' . $domain . '/authorized-recipients</>');
            $this->line('');
            $this->line('  Option B — Use a custom Mailgun domain (full production setup):');
            $this->line('  <fg=cyan>  https://app.mailgun.com/mg/sending/domains</>');
            $this->line('');
            $this->line('  Option C — Use log driver for local dev (no real emails):');
            $this->line('  <fg=cyan>  MAIL_MAILER=log</>');
            return;
        }

        if (str_contains($message, '403')) {
            $this->line('  <fg=yellow>HTTP 403 — authentication or permission issue. Check your API key.</>');
            return;
        }

        if (str_contains($message, 'Connection refused') || str_contains($message, 'getaddrinfo')) {
            $this->line('  <fg=yellow>Network error — cannot reach the mail server. Check MAIL_HOST / firewall.</>');
            return;
        }
    }
}
