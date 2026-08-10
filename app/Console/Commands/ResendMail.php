<?php

namespace App\Console\Commands;

use App\Mail\BoundaryDigestMail;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use App\Services\BoundaryDigestMatchService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Resends any App\Mail mailable to a specific recipient — for ops use when a
 * transactional email failed to deliver (mail provider outage, a bounced
 * inbox now fixed, a voter asking "I never got my welcome email"…) without
 * waiting for whatever originally triggered it to happen again.
 *
 * Constructor parameters typed as User/Voter/Politician/Citizen are resolved
 * automatically from the recipient (or from --set=param=id for an unrelated
 * model). Anything else — amounts, reasons, transaction IDs — must be
 * supplied via --set=key=value; the command lists exactly what's missing
 * rather than guessing. BoundaryDigestMail is special-cased to regenerate
 * live digest content instead of requiring it be reconstructed by hand.
 *
 * Examples:
 *   php artisan mail:resend welcome voter@example.com
 *   php artisan mail:resend boundary-digest 42 --queue
 *   php artisan mail:resend kyc-rejected voter@example.com --set=reason="Blurry ID photo"
 */
class ResendMail extends Command
{
    protected $signature = 'mail:resend
                                {mailable? : Short name (e.g. "welcome", "boundary-digest") or FQCN}
                                {recipient? : User/Voter ID or email address}
                                {--set=* : Extra constructor args not resolvable from the recipient, as key=value}
                                {--queue : Queue the email instead of sending synchronously}
                                {--list : List all available mailables and exit}';

    protected $description = 'Resend a transactional email to a specific user (ops recovery tool)';

    public function handle(BoundaryDigestMatchService $matchService): int
    {
        if ($this->option('list') || ! $this->argument('mailable') || ! $this->argument('recipient')) {
            $this->listMailables();
            return $this->option('list') ? self::SUCCESS : self::INVALID;
        }

        $class = $this->resolveMailableClass((string) $this->argument('mailable'));
        if ($class === null) {
            $this->error("Unknown mailable: {$this->argument('mailable')}");
            $this->listMailables();
            return self::FAILURE;
        }

        [$recipient, $email] = $this->resolveRecipient((string) $this->argument('recipient'));
        if ($recipient === null) {
            $this->error("No user or voter found for \"{$this->argument('recipient')}\".");
            return self::FAILURE;
        }

        try {
            $mailable = $class === BoundaryDigestMail::class
                ? $this->buildBoundaryDigest($recipient, $matchService)
                : $this->buildGeneric($class, $recipient);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($mailable === null) {
            $this->warn('Nothing to send — no new digest content for this recipient right now.');
            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            Mail::to($email)->queue($mailable);
        } else {
            Mail::to($email)->send($mailable);
        }

        Log::info('mail:resend sent', [
            'mailable'  => $class,
            'recipient' => $email,
            'queued'    => (bool) $this->option('queue'),
        ]);

        $this->info(($this->option('queue') ? 'Queued' : 'Sent') . " {$class} to {$email}.");

        return self::SUCCESS;
    }

    private function listMailables(): void
    {
        $this->line('Usage: php artisan mail:resend {mailable} {recipient} [--set=key=value]... [--queue]');
        $this->line('');
        $this->line('Available mailables:');
        foreach (File::files(app_path('Mail')) as $file) {
            $name = $file->getFilenameWithoutExtension();
            $short = Str::kebab(Str::endsWith($name, 'Mail') ? substr($name, 0, -4) : $name);
            $this->line("  {$short}");
        }
    }

    private function resolveMailableClass(string $input): ?string
    {
        foreach ([
            $input,
            'App\\Mail\\' . $input,
            'App\\Mail\\' . Str::studly(str_replace(['-', '_'], ' ', $input)) . 'Mail',
            'App\\Mail\\' . Str::studly(str_replace(['-', '_'], ' ', $input)),
        ] as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, Mailable::class)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{0: User|Voter|null, 1: string|null}
     */
    private function resolveRecipient(string $input): array
    {
        $user = is_numeric($input) ? User::find((int) $input) : User::where('email', $input)->first();
        if ($user) {
            return [$user, $user->email];
        }

        $voter = is_numeric($input) ? Voter::find((int) $input) : Voter::where('email', $input)->first();
        if ($voter && $voter->email) {
            return [$voter, $voter->email];
        }

        return [null, null];
    }

    private function buildBoundaryDigest(User|Voter $recipient, BoundaryDigestMatchService $matchService): ?Mailable
    {
        $voter = $recipient instanceof Voter ? $recipient : $recipient->voter;
        if (! $voter) {
            throw new RuntimeException('BoundaryDigestMail requires a voter — recipient has no voter profile.');
        }

        $since = $voter->last_digest_sent_at ?? now()->subDays(30);
        $content = $matchService->contentForVoter($voter, $since);

        if ($content['sections'] === []) {
            return null;
        }

        $periodLabel = $since->format('M j') . ' – ' . now()->format('M j, Y');

        return new BoundaryDigestMail($voter, $content['sections'], $periodLabel, $content['remaining']);
    }

    /**
     * Reflection-based build for any other mailable: model-typed constructor
     * parameters (User/Voter/Politician/Citizen, or any Eloquent model via
     * --set=param=id) are resolved automatically; scalar/array parameters
     * come from --set=key=value or a constructor default.
     */
    private function buildGeneric(string $class, User|Voter $recipient): Mailable
    {
        $extra = $this->parseSetOptions();
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

            if ($typeName !== null && is_a($recipient, $typeName)) {
                $args[] = $recipient;
                continue;
            }

            if ($typeName === Politician::class || $typeName === Citizen::class || $typeName === Voter::class) {
                $related = match ($typeName) {
                    Politician::class => $recipient instanceof User ? $recipient->politician : null,
                    Citizen::class => $recipient instanceof User ? $recipient->citizen : null,
                    Voter::class => $recipient instanceof Voter ? $recipient : $recipient->voter,
                };

                if (! $related) {
                    throw new RuntimeException("{$class} needs a {$typeName}, but the recipient has no matching profile.");
                }

                $args[] = $related;
                continue;
            }

            if ($typeName !== null && is_subclass_of($typeName, Model::class) && array_key_exists($param->getName(), $extra)) {
                $model = $typeName::find($extra[$param->getName()]);
                if (! $model) {
                    throw new RuntimeException("Could not find {$typeName} with id '{$extra[$param->getName()]}' (--set={$param->getName()}=...).");
                }
                $args[] = $model;
                continue;
            }

            if (array_key_exists($param->getName(), $extra)) {
                $args[] = $this->castSetValue($extra[$param->getName()], $type instanceof ReflectionNamedType ? $type : null);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new RuntimeException(
                "{$class} constructor needs '{$param->getName()}' (" . ($typeName ?? (string) $type) . ') — '
                . "pass it with --set={$param->getName()}=value."
            );
        }

        return $reflection->newInstance(...$args);
    }

    /** @return array<string,string> */
    private function parseSetOptions(): array
    {
        $out = [];
        foreach ((array) $this->option('set') as $pair) {
            [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, null);
            if ($key !== null && $value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function castSetValue(string $value, ?ReflectionNamedType $type): mixed
    {
        return match ($type?->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => array_map('trim', explode(',', $value)),
            default => $value,
        };
    }
}
