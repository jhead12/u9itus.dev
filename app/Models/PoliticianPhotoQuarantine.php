<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoliticianPhotoQuarantine extends Model
{
    protected $fillable = [
        'politician_id',
        'photo_url',
        'photo_url_hash',
        'status',
        'validator',
        'confidence',
        'reason',
        'meta',
        'resolved_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (PoliticianPhotoQuarantine $row): void {
            $row->photo_url_hash = hash('sha256', strtolower(trim((string) $row->photo_url)));
        });
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:3',
            'meta' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }
}
