<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Models\PoliticianPhotoQuarantine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ValidatePoliticianProfilePhotos extends Command
{
    protected $signature = 'politicians:validate-profile-photos
        {--state=              : Two-letter state code to filter}
        {--limit=500           : Max politicians to inspect per run}
        {--include-claimed     : Include claimed profiles (default scans unclaimed only)}
        {--fix-invalid         : Legacy alias. With quarantine flow this only clears when --auto-clear is also set}
        {--quarantine-only     : Write invalid/unknown records to quarantine queue (default behavior)}
        {--auto-clear          : Clear profile_photo_url when invalid confidence exceeds threshold}
        {--dry-run             : Report only, no database writes}
        {--skip-ai             : Use URL heuristics only (no Anthropic call)}
        {--require-ai          : Fail when ANTHROPIC_API_KEY is missing}
        {--min-confidence=0.70 : AI confidence threshold for auto-clearing}
        {--fail-on-invalid     : Return non-zero exit code if invalid photos are found}';

    protected $description = 'Validate politician profile photos and flag/clear likely logos or non-face images.';

    private const USER_AGENT = 'U9itus-photo-validator/1.0 (+https://u9itus.dev/about)';
    private const MAX_IMAGE_BYTES = 1500000; // Keep inference payloads small and predictable.

    public function handle(): int
    {
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $limit = max(1, (int) ($this->option('limit') ?? 500));
        $includeClaimed = (bool) $this->option('include-claimed');
        $fixInvalid = (bool) $this->option('fix-invalid');
        $quarantineOnly = (bool) $this->option('quarantine-only');
        $autoClear = (bool) $this->option('auto-clear');
        $dryRun = (bool) $this->option('dry-run');
        $skipAi = (bool) $this->option('skip-ai');
        $requireAi = (bool) $this->option('require-ai');
        $failOnInvalid = (bool) $this->option('fail-on-invalid');
        $minConfidence = max(0.0, min(1.0, (float) $this->option('min-confidence')));
        $apiKey = (string) config('services.anthropic.api_key');

        if ($dryRun) {
            $this->line('<fg=yellow>[dry-run] No database writes will occur.</>');
        }

        if ($fixInvalid && ! $autoClear) {
            $this->line('<fg=yellow>[info] --fix-invalid is quarantine-first unless --auto-clear is also set.</>');
        }

        if (($autoClear || $fixInvalid) && $dryRun) {
            $this->line('<fg=yellow>[dry-run] clear mode requested; would clear invalid profile_photo_url values when eligible.</>');
        }

        if (! $skipAi && $apiKey === '' && $requireAi) {
            $this->error('ANTHROPIC_API_KEY is missing and --require-ai was set.');
            return self::FAILURE;
        }

        if (! $skipAi && $apiKey === '') {
            $this->warn('ANTHROPIC_API_KEY missing: running heuristic-only validation.');
            $skipAi = true;
        }

        $query = Politician::query()
            ->whereNotNull('profile_photo_url')
            ->where('profile_photo_url', '!=', '')
            ->when(! $includeClaimed, fn ($q) => $q->whereNull('user_id'))
            ->when($state, fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state]))
            ->orderBy('id')
            ->limit($limit);

        $politicians = $query->get(['id', 'full_name', 'state', 'political_office', 'profile_photo_url']);

        $total = $politicians->count();
        $valid = 0;
        $invalid = 0;
        $unknown = 0;
        $cleared = 0;
        $quarantined = 0;

        $this->line("Inspecting {$total} politician profile photo(s)...\n");

        foreach ($politicians as $politician) {
            $url = trim((string) $politician->profile_photo_url);
            $result = $this->validatePhotoUrl($url, ! $skipAi);

            if ($result['status'] === 'valid') {
                $valid++;
                $this->line("  <fg=green>✓</> #{$politician->id} {$politician->full_name}");

                if (! $dryRun) {
                    $politician->update([
                        'profile_photo_status' => 'verified',
                        'profile_photo_validation_confidence' => (float) ($result['confidence'] ?? 1.0),
                        'profile_photo_last_validated_at' => now(),
                    ]);
                }

                continue;
            }

            if ($result['status'] === 'invalid') {
                $invalid++;
                $reason = $result['reason'] ?? 'non-face image';
                $confidence = number_format((float) ($result['confidence'] ?? 0), 2);
                $this->line("  <fg=red>✗</> #{$politician->id} {$politician->full_name} ({$confidence}) — {$reason}");

                if (! $dryRun && ($quarantineOnly || $fixInvalid || $autoClear)) {
                    $this->upsertQuarantine(
                        politician: $politician,
                        photoUrl: $url,
                        status: 'pending',
                        validator: (string) ($result['validator'] ?? 'anthropic'),
                        confidence: (float) ($result['confidence'] ?? 0),
                        reason: $reason,
                        meta: $result['meta'] ?? null,
                    );
                    $quarantined++;

                    $politician->update([
                        'profile_photo_status' => 'quarantined',
                        'profile_photo_validation_confidence' => (float) ($result['confidence'] ?? 0),
                        'profile_photo_last_validated_at' => now(),
                    ]);
                }

                if (($autoClear || $fixInvalid) && ! $dryRun && (float) ($result['confidence'] ?? 0) >= $minConfidence) {
                    $politician->update([
                        'profile_photo_url' => null,
                        'profile_photo_status' => 'auto_cleared',
                        'profile_photo_validation_confidence' => (float) ($result['confidence'] ?? 0),
                        'profile_photo_last_validated_at' => now(),
                    ]);

                    $this->upsertQuarantine(
                        politician: $politician,
                        photoUrl: $url,
                        status: 'auto_cleared',
                        validator: (string) ($result['validator'] ?? 'anthropic'),
                        confidence: (float) ($result['confidence'] ?? 0),
                        reason: $reason,
                        meta: $result['meta'] ?? null,
                    );
                    $cleared++;
                }
                continue;
            }

            $unknown++;
            $reason = $result['reason'] ?? 'could not classify';
            $this->line("  <fg=yellow>?</> #{$politician->id} {$politician->full_name} — {$reason}");

            if (! $dryRun && $quarantineOnly) {
                $this->upsertQuarantine(
                    politician: $politician,
                    photoUrl: $url,
                    status: 'pending',
                    validator: (string) ($result['validator'] ?? 'heuristic'),
                    confidence: (float) ($result['confidence'] ?? 0),
                    reason: $reason,
                    meta: $result['meta'] ?? null,
                );
                $quarantined++;

                $politician->update([
                    'profile_photo_status' => 'quarantined',
                    'profile_photo_validation_confidence' => (float) ($result['confidence'] ?? 0),
                    'profile_photo_last_validated_at' => now(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary: valid={$valid}, invalid={$invalid}, unknown={$unknown}, quarantined={$quarantined}, cleared={$cleared}");

        if ($failOnInvalid && $invalid > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{status: 'valid'|'invalid'|'unknown', confidence?: float, reason?: string}
     */
    private function validatePhotoUrl(string $url, bool $useAi): array
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return ['status' => 'invalid', 'confidence' => 1.0, 'reason' => 'invalid url', 'validator' => 'heuristic'];
        }

        if ($this->looksLikeLogoOrSymbolUrl($url)) {
            return ['status' => 'invalid', 'confidence' => 0.95, 'reason' => 'url indicates logo/symbol', 'validator' => 'heuristic'];
        }

        if (! $useAi) {
            return ['status' => 'unknown', 'reason' => 'heuristic-only pass (no AI)', 'validator' => 'heuristic'];
        }

        $image = $this->fetchImage($url);
        if (! $image) {
            return ['status' => 'unknown', 'reason' => 'image fetch failed', 'validator' => 'heuristic'];
        }

        $ai = $this->classifyImageWithAnthropic($image['bytes'], $image['mime']);
        if (! $ai) {
            return ['status' => 'unknown', 'reason' => 'AI classification failed', 'validator' => 'anthropic'];
        }

        $isFace = (bool) ($ai['is_face'] ?? false);
        $isLogo = (bool) ($ai['is_logo_or_symbol'] ?? false);
        $confidence = (float) ($ai['confidence'] ?? 0.0);
        $reason = (string) ($ai['reason'] ?? '');

        if ($isFace && ! $isLogo) {
            return ['status' => 'valid', 'confidence' => $confidence, 'reason' => $reason, 'validator' => 'anthropic', 'meta' => $ai];
        }

        if ($isLogo || ! $isFace) {
            return [
                'status' => 'invalid',
                'confidence' => max($confidence, $isLogo ? 0.85 : $confidence),
                'reason' => $reason !== '' ? $reason : ($isLogo ? 'logo/symbol image' : 'no clear face'),
                'validator' => 'anthropic',
                'meta' => $ai,
            ];
        }

        return ['status' => 'unknown', 'reason' => 'ambiguous AI output', 'validator' => 'anthropic', 'meta' => $ai];
    }

    private function upsertQuarantine(
        Politician $politician,
        string $photoUrl,
        string $status,
        string $validator,
        float $confidence,
        ?string $reason,
        mixed $meta,
    ): void {
        $photoUrlHash = hash('sha256', strtolower(trim($photoUrl)));

        PoliticianPhotoQuarantine::updateOrCreate(
            [
                'politician_id' => $politician->id,
                'photo_url_hash' => $photoUrlHash,
            ],
            [
                'photo_url' => $photoUrl,
                'status' => $status,
                'validator' => $validator,
                'confidence' => $confidence,
                'reason' => $reason,
                'meta' => is_array($meta) ? $meta : null,
                'resolved_at' => in_array($status, ['approved', 'rejected', 'auto_cleared'], true) ? now() : null,
            ]
        );
    }

    private function looksLikeLogoOrSymbolUrl(string $url): bool
    {
        $haystack = strtolower($url);
        foreach (['logo', 'seal', 'flag', 'coat_of_arms', 'wordmark', 'emblem', 'icon'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{bytes: string, mime: string}|null
     */
    private function fetchImage(string $url): ?array
    {
        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);

            if (! $response->ok()) {
                return null;
            }

            $mime = strtolower((string) $response->header('Content-Type'));
            $mime = trim(explode(';', $mime)[0]);
            if (! str_starts_with($mime, 'image/')) {
                return null;
            }

            $bytes = (string) $response->body();
            if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
                return null;
            }

            return ['bytes' => $bytes, 'mime' => $mime];
        } catch (\Throwable $e) {
            Log::debug('politicians:validate-profile-photos fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array{is_face?: bool, is_logo_or_symbol?: bool, confidence?: float, reason?: string}|null
     */
    private function classifyImageWithAnthropic(string $bytes, string $mime): ?array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'x-api-key' => (string) config('services.anthropic.api_key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-haiku-4-5'),
                    'max_tokens' => 120,
                    'temperature' => 0,
                    'system' => 'You classify profile photos for civic candidate accounts. Return ONLY compact JSON with keys: is_face (boolean), is_logo_or_symbol (boolean), confidence (0-1), reason (short string).',
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Determine whether this image is primarily a human face/headshot suitable for a politician profile photo. Reject logos, seals, flags, symbols, and text-only graphics. Output JSON only.',
                            ],
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mime,
                                    'data' => base64_encode($bytes),
                                ],
                            ],
                        ],
                    ]],
                ]);

            if (! $response->ok()) {
                Log::warning('politicians:validate-profile-photos anthropic error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = (string) ($response->json('content.0.text') ?? '');
            if ($text === '') {
                return null;
            }

            if (preg_match('/\{.*\}/s', $text, $m) !== 1) {
                return null;
            }

            $decoded = json_decode($m[0], true);
            if (! is_array($decoded)) {
                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::warning('politicians:validate-profile-photos anthropic failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
