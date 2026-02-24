<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Phase 13 — A policy position / platform plank displayed on the politician's
 * public profile page.
 */
class PoliticianInitiative extends Model
{
    use HasFactory;

    protected $table = 'politician_initiatives';

    protected $fillable = [
        'politician_id',
        'title',
        'description',
        'icon',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'   => 'integer',
            'is_published' => 'boolean',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }
}
