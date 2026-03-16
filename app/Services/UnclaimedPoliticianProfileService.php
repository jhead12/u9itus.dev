<?php

namespace App\Services;

use App\Models\Politician;
use App\Models\User;

class UnclaimedPoliticianProfileService
{
    /**
     * Claim the best matching unclaimed politician profile for a user,
     * or create a new profile if no strong match exists.
     *
     * @param  array<string, mixed>  $payload
     */
    public function claimOrCreate(User $user, array $payload): Politician
    {
        $existing = Politician::where('user_id', $user->id)->first();

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        $match = $this->findBestUnclaimedMatch($payload);

        if ($match) {
            $this->mergeClaimedProfile($match, $user, $payload);

            return $match;
        }

        return Politician::create(array_merge($payload, [
            'user_id' => $user->id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findBestUnclaimedMatch(array $payload): ?Politician
    {
        $fullName = $this->normalize((string) ($payload['full_name'] ?? ''));
        $state = strtoupper(trim((string) ($payload['state'] ?? '')));
        $office = $this->normalize((string) ($payload['political_office'] ?? ''));

        if ($fullName === '' || $state === '') {
            return null;
        }

        $candidates = Politician::query()
            ->whereNull('user_id')
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->limit(50)
            ->get();

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $candidateName = $this->normalize((string) $candidate->full_name);
            if ($candidateName === '') {
                continue;
            }

            similar_text($fullName, $candidateName, $namePercent);
            $nameScore = $namePercent / 100;

            $candidateOffice = $this->normalize((string) ($candidate->political_office ?? ''));
            $officeScore = 0.5;
            if ($office !== '' && $candidateOffice !== '') {
                similar_text($office, $candidateOffice, $officePercent);
                $officeScore = $officePercent / 100;
            }

            $score = ($nameScore * 0.8) + ($officeScore * 0.2);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $bestScore >= 0.90 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function mergeClaimedProfile(Politician $politician, User $user, array $payload): void
    {
        $politician->user_id = $user->id;

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Keep imported profile richness where possible, but allow core signup fields.
            if (in_array($key, ['full_name', 'political_office', 'party_affiliation', 'governance_level', 'state', 'city'], true)) {
                $politician->{$key} = $value;
                continue;
            }

            if (empty($politician->{$key})) {
                $politician->{$key} = $value;
            }
        }

        $politician->save();
    }

    protected function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
}
