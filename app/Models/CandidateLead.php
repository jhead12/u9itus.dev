<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateLead extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROMOTED = 'promoted';
    public const STATUS_AUTO_CLEARED = 'auto_cleared';

    protected $fillable = [
        'source_key',
        'full_name',
        'state',
        'office_hint',
        'source_url',
        'source_hash',
        'discovery_context',
        'discovered_at',
        'status',
        'verifier_key',
        'confidence',
        'reason',
        'verified_payload',
        'election_candidate_record_id',
        'resolved_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (CandidateLead $lead): void {
            $lead->source_hash = hash('sha256', strtolower(trim((string) $lead->source_url)));
        });
    }

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
            'confidence' => 'decimal:3',
            'verified_payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function candidateRecord(): BelongsTo
    {
        return $this->belongsTo(ElectionCandidateRecord::class, 'election_candidate_record_id');
    }
}
