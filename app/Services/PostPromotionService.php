<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\CitizenCredit;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Promote a published blog post by spending wallet credits.
 *
 * Supports both Citizen and Politician authors. The daily promotion cost is
 * read from the platform setting `post_promotion_daily_credits` (default $5).
 */
class PostPromotionService
{
    /**
     * Promote a post for a given number of days.
     *
     * @throws \InvalidArgumentException if inputs are invalid
     * @throws \LogicException if author has insufficient credits
     */
    public function promote(Post $post, int $days): void
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('Promotion must be at least one day.');
        }

        if (! $post->isPublished()) {
            throw new \LogicException('Only published posts can be promoted.');
        }

        $dailyCost = (float) PlatformSettingsService::get('post_promotion_daily_credits', null, 5.0);
        $totalCost = $dailyCost * $days;

        $author = $post->author;
        if (! $author) {
            throw new \LogicException('Post has no author.');
        }

        if (! $author instanceof Citizen && ! $author instanceof Politician) {
            throw new \LogicException('Unsupported author type for promotion.');
        }

        DB::transaction(function () use ($author, $totalCost, $post, $days): void {
            if ($author instanceof Citizen) {
                $this->deductFromCitizen($author, $totalCost, $post);
            } else {
                $this->deductFromPolitician($author, $totalCost, $post);
            }

            $until = $post->promoted_until && $post->promoted_until->gt(now())
                ? $post->promoted_until->addDays($days)
                : now()->addDays($days);

            $post->update([
                'is_promoted' => true,
                'promoted_until' => $until,
                'credit_spent' => (float) $post->credit_spent + $totalCost,
            ]);
        });

        Log::info('Post promoted', [
            'post_id' => $post->id,
            'author_type' => $post->author_type,
            'author_id' => $post->author_id,
            'days' => $days,
            'cost' => $totalCost,
            'until' => $post->promoted_until,
        ]);
    }

    private function deductFromCitizen(Citizen $citizen, float $amount, Post $post): void
    {
        DB::transaction(function () use ($citizen, $amount, $post): void {
            $citizen = Citizen::lockForUpdate()->findOrFail($citizen->id);

            $latest = CitizenCredit::where('citizen_id', $citizen->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $balance = $latest ? (float) $latest->balance_after : 0.0;

            if ($balance < $amount) {
                throw new \LogicException(
                    'Insufficient credits. You need $'.number_format($amount, 2).
                    ' but have $'.number_format($balance, 2).'.'
                );
            }

            CitizenCredit::create([
                'citizen_id' => $citizen->id,
                'transaction_type' => 'post_promotion',
                'amount' => -$amount,
                'balance_after' => $balance - $amount,
                'description' => "Promoted blog post #{$post->id}",
                'metadata' => [
                    'post_uuid' => $post->uuid,
                    'post_slug' => $post->slug,
                ],
            ]);

            $citizen->syncCreditBalance();
        });
    }

    private function deductFromPolitician(Politician $politician, float $amount, Post $post): void
    {
        DB::transaction(function () use ($politician, $amount, $post): void {
            $politician = Politician::lockForUpdate()->findOrFail($politician->id);

            $latest = PoliticianCredit::where('politician_id', $politician->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $balance = $latest ? (float) $latest->balance_after : 0.0;

            if ($balance < $amount) {
                throw new \LogicException(
                    'Insufficient credits. You need $'.number_format($amount, 2).
                    ' but have $'.number_format($balance, 2).'.'
                );
            }

            PoliticianCredit::create([
                'politician_id' => $politician->id,
                'transaction_type' => 'post_promotion',
                'amount' => -$amount,
                'balance_after' => $balance - $amount,
                'description' => "Promoted blog post #{$post->id}",
                'metadata' => [
                    'post_uuid' => $post->uuid,
                    'post_slug' => $post->slug,
                ],
            ]);

            $politician->syncCreditBalance();
        });
    }

    /**
     * Extend an existing promotion. Convenience wrapper around promote().
     */
    public function extend(Post $post, int $days): void
    {
        $this->promote($post, $days);
    }

    /**
     * Stop an active promotion immediately (does not refund credits).
     */
    public function cancel(Post $post): void
    {
        $post->update([
            'is_promoted' => false,
            'promoted_until' => null,
        ]);
    }

    /**
     * Daily cost for post promotion in credits.
     */
    public static function dailyCost(): float
    {
        return (float) PlatformSettingsService::get('post_promotion_daily_credits', null, 5.0);
    }

    /**
     * Total cost to promote for a given number of days.
     */
    public static function costForDays(int $days): float
    {
        return self::dailyCost() * max(1, $days);
    }
}
