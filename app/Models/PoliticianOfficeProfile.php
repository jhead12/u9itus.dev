<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores civic education data about a politician's office.
 * Displayed to voters as an informational popup while watching
 * a campaign video — helping communities understand how each
 * elected or appointed position affects their daily lives.
 */
class PoliticianOfficeProfile extends Model
{
    use HasFactory;

    protected $table = 'politician_office_profiles';

    protected $fillable = [
        'politician_id',
        'office_title',
        'governance_level',
        'jurisdiction',
        'how_elected_or_appointed',
        'term_length_years',
        'seats_in_body',
        'annual_salary_min',
        'annual_salary_max',
        'salary_currency',
        'salary_source_note',
        'role_summary',
        'community_impact',
        'key_duties',
        'powers_and_limits',
        'source_url',
        'is_verified',
        'data_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'key_duties'        => 'array',
            'powers_and_limits' => 'array',
            'is_verified'       => 'boolean',
            'data_verified_at'  => 'datetime',
            'term_length_years' => 'integer',
            'seats_in_body'     => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Human-readable salary range formatted in USD.
     * Stored as cents to avoid floating-point issues.
     */
    public function getSalaryRangeAttribute(): ?string
    {
        if (! $this->annual_salary_min && ! $this->annual_salary_max) {
            return null;
        }

        $format = fn (int $cents) => '$' . number_format($cents / 100);

        if ($this->annual_salary_min === $this->annual_salary_max || ! $this->annual_salary_max) {
            return $format($this->annual_salary_min ?? $this->annual_salary_max);
        }

        if (! $this->annual_salary_min) {
            return $format($this->annual_salary_max);
        }

        return $format($this->annual_salary_min) . ' – ' . $format($this->annual_salary_max);
    }

    /**
     * Returns a voter-safe JSON payload for the info popup.
     * Excludes internal fields (politician_id, audit timestamps, etc.)
     */
    public function toVoterPayload(): array
    {
        return [
            'office_title'              => $this->office_title,
            'governance_level'          => $this->governance_level,
            'jurisdiction'              => $this->jurisdiction,
            'how_elected_or_appointed'  => $this->how_elected_or_appointed,
            'term_length_years'         => $this->term_length_years,
            'seats_in_body'             => $this->seats_in_body,
            'salary_range'              => $this->salary_range,
            'salary_source_note'        => $this->salary_source_note,
            'role_summary'              => $this->role_summary,
            'community_impact'          => $this->community_impact,
            'key_duties'                => $this->key_duties ?? [],
            'powers_and_limits'         => $this->powers_and_limits ?? [],
            'source_url'                => $this->source_url,
            'is_verified'               => $this->is_verified,
        ];
    }
}
