<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Email Template
 *
 * Stores admin-editable configuration for every transactional email.
 * - `subject_override` — replaces the hard-coded subject in the Mailable
 * - `body_override`    — full HTML replacement for the Blade template (optional)
 * - `is_active`        — when false the notification will be silently skipped
 */
class EmailTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'subject_override',
        'preview_text',
        'body_override',
        'available_variables',
        'is_active',
        'last_edited_by',
    ];

    protected $casts = [
        'available_variables' => 'array',
        'is_active'           => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Find a template by key (returns null safely if not yet seeded).
     */
    public static function forKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    /**
     * Resolve the effective subject for a given Mailable default.
     */
    public function effectiveSubject(string $mailableDefault): string
    {
        return $this->subject_override ?: $mailableDefault;
    }

    /**
     * Returns whether the admin has supplied a custom HTML body.
     */
    public function hasBodyOverride(): bool
    {
        return !empty($this->body_override);
    }

    /**
     * Human-readable category label.
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            'kyc'      => 'Identity / KYC',
            'campaign' => 'Campaign',
            'billing'  => 'Billing & Credits',
            'payout'   => 'Payouts',
            'account'  => 'Account / Auth',
            'admin'    => 'Admin Alerts',
            default    => ucfirst($this->category),
        };
    }
}
