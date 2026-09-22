<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoliticianChatterModerationLog extends Model
{
    protected $fillable = [
        'politician_chatter_item_id', 'admin_user_id', 'action', 'from_status', 'to_status', 'changes', 'note',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PoliticianChatterItem::class, 'politician_chatter_item_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
