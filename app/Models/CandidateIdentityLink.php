<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateIdentityLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'politician_id',
        'election_candidate_record_id',
        'match_score',
        'link_source',
        'linked_by_user_id',
        'match_breakdown',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:4',
            'match_breakdown' => 'array',
            'linked_at' => 'datetime',
        ];
    }

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function candidateRecord(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ElectionCandidateRecord::class, 'election_candidate_record_id');
    }

    public function linkedByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }
}
