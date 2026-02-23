<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit record of every admin action on a political campaign.
 *
 * @property int    $id
 * @property int    $campaign_id
 * @property int    $admin_id
 * @property string $action       edited | stopped | reactivated | approved | rejected
 * @property string|null $reason
 * @property array|null  $changes  { field: { old: x, new: y } }
 */
class CampaignAuditLog extends Model
{
    // Audit logs are immutable — no updates.
    public const UPDATED_AT = null;

    protected $fillable = [
        'campaign_id',
        'admin_id',
        'action',
        'reason',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PoliticalCampaign::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Human-readable label for the action.
     */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'edited'      => 'Edited',
            'stopped'     => 'Stopped',
            'reactivated' => 'Reactivated',
            'approved'    => 'Approved',
            'rejected'    => 'Rejected',
            default       => ucfirst($this->action),
        };
    }

    /**
     * Tailwind badge color for the action.
     */
    public function actionColor(): string
    {
        return match ($this->action) {
            'approved'    => 'emerald',
            'rejected'    => 'red',
            'stopped'     => 'red',
            'reactivated' => 'emerald',
            'edited'      => 'amber',
            default       => 'slate',
        };
    }

    /**
     * Build a diff array from two attribute arrays.
     *
     * @param  array $before
     * @param  array $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function buildDiff(array $before, array $after): array
    {
        $diff = [];
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            // Normalise for comparison (enums → value string)
            $oldCmp = $oldValue instanceof \BackedEnum ? $oldValue->value : $oldValue;
            $newCmp = $newValue instanceof \BackedEnum ? $newValue->value : $newValue;
            if ($oldCmp !== $newCmp) {
                $diff[$field] = ['old' => $oldCmp, 'new' => $newCmp];
            }
        }
        return $diff;
    }
}
