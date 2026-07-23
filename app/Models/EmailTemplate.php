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
     * Resolve the subject/message/body trio for a referral share template.
     *
     * Uses the admin override only when the template exists AND is active;
     * otherwise falls back to the provided defaults. The body is the
     * resolved message followed by the referral URL on its own line.
     *
     * Centralizes the resolution logic that the voter/politician/onboarding
     * referral views previously duplicated inline.
     *
     * @return array{subject: string, message: string, body: string}
     */
    public static function shareCopy(string $key, string $url, string $fallbackSubject, string $fallbackMessage): array
    {
        $tpl = static::forKey($key);

        $subject = ($tpl && $tpl->is_active && $tpl->subject_override)
            ? $tpl->subject_override
            : $fallbackSubject;
        $message = ($tpl && $tpl->is_active && $tpl->body_override)
            ? $tpl->body_override
            : $fallbackMessage;

        return [
            'subject' => $subject,
            'message' => $message,
            'body'    => $message . "\n\n" . $url,
        ];
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
     * Resolve the share message text, falling back to $default when no
     * body_override is stored.  Optionally replaces simple {{variable}}
     * placeholders using the provided $bindings array.
     *
     * @param  string  $default
     * @param  array<string,string>  $bindings  e.g. ['{{politician.name}}' => 'John Smith']
     */
    public function effectiveShareMessage(string $default, array $bindings = []): string
    {
        $text = $this->body_override ?: $default;

        if ($bindings) {
            $text = str_replace(array_keys($bindings), array_values($bindings), $text);
        }

        return $text;
    }

    /**
     * Resolve the share title, falling back to $default when no
     * subject_override is stored.
     */
    public function effectiveShareTitle(string $default): string
    {
        return $this->subject_override ?: $default;
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
            'referral' => 'Referral / Sharing',
            default    => ucfirst($this->category),
        };
    }
}
