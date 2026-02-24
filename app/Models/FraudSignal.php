<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Individual fraud signal raised against a voter — Phase 8.
 *
 * @property int         $id
 * @property int         $voter_id
 * @property string|null $view_session_uuid
 * @property string      $signal_type
 * @property string|null $description
 * @property int         $score_impact
 * @property string|null $ip_address
 * @property string|null $device_fingerprint
 * @property string|null $provider
 * @property array|null  $metadata
 * @property bool        $is_resolved
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null    $resolved_by
 * @property string|null $resolution_note
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FraudSignal extends Model
{
    use HasFactory;

    protected $table = 'fraud_signals';

    protected $fillable = [
        'voter_id',
        'view_session_uuid',
        'signal_type',
        'description',
        'score_impact',
        'ip_address',
        'device_fingerprint',
        'provider',
        'metadata',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'metadata'    => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function voter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function resolvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeForVoter($query, int $voterId)
    {
        return $query->where('voter_id', $voterId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('signal_type', $type);
    }
}
