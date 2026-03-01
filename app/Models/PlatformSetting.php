<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Platform-wide dynamic settings — pricing, commissions, thresholds, fraud limits.
 * 
 * Allows admins to adjust business logic values without code changes.
 * Supports time-bound promotions, user-tier specific overrides (early adopters), 
 * and A/B testing.
 */
class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'category',
        'effective_from',
        'effective_until',
        'user_tier',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Check if this setting is currently active based on effective dates.
     */
    public function isEffective(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->effective_from && $now->isBefore($this->effective_from)) {
            return false;
        }

        if ($this->effective_until && $now->isAfter($this->effective_until)) {
            return false;
        }

        return true;
    }

    /**
     * Cast the value to its proper type.
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'float' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $this->value,
            default => $this->value,
        };
    }

    // ── Query Scopes ────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            });
    }

    public function scopeForUser($query, ?string $userTier = null)
    {
        return $query->where(function ($q) use ($userTier) {
            $q->whereNull('user_tier')
                ->when($userTier, fn($q) => $q->orWhere('user_tier', $userTier));
        });
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
