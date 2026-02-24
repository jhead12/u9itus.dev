<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Phase 13 — Politician public profile page theme configuration.
 *
 * One-to-one with politicians: a politician can have exactly one page record.
 * Missing records are treated as "all defaults"; auto-created on first save.
 */
class PoliticianPage extends Model
{
    use HasFactory;

    protected $table = 'politician_pages';

    protected $fillable = [
        'politician_id',
        'layout_preset',
        'primary_color',
        'accent_color',
        'background_style',
        'hero_banner_url',
        'show_bio',
        'show_initiatives',
        'show_campaigns',
        'show_contact',
        'custom_cta_text',
        'custom_cta_url',
    ];

    protected function casts(): array
    {
        return [
            'show_bio'          => 'boolean',
            'show_initiatives'  => 'boolean',
            'show_campaigns'    => 'boolean',
            'show_contact'      => 'boolean',
        ];
    }

    // ── Layout preset options ─────────────────────────────────────────────

    public const LAYOUTS = ['classic', 'modern', 'bold', 'minimal'];

    // ── Background style options ──────────────────────────────────────────

    public const BACKGROUNDS = ['dark', 'light', 'gradient', 'image'];

    // ── Defaults (used when no page record exists yet) ────────────────────

    public static function defaults(int $politicianId): array
    {
        return [
            'politician_id'    => $politicianId,
            'layout_preset'    => 'classic',
            'primary_color'    => '#1e40af',
            'accent_color'     => '#f59e0b',
            'background_style' => 'dark',
            'hero_banner_url'  => null,
            'show_bio'         => true,
            'show_initiatives' => true,
            'show_campaigns'   => true,
            'show_contact'     => true,
            'custom_cta_text'  => null,
            'custom_cta_url'   => null,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Returns CSS variable declarations for inline <style> injection.
     * Colors are validated to be hex values before output.
     */
    public function cssVariables(): string
    {
        $primary = $this->validHex($this->primary_color, '#1e40af');
        $accent  = $this->validHex($this->accent_color,  '#f59e0b');

        return "--p13-primary:{$primary};--p13-accent:{$accent};";
    }

    private function validHex(string|null $value, string $fallback): string
    {
        if ($value && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return $value;
        }
        return $fallback;
    }
}
