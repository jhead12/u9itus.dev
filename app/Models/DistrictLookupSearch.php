<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DistrictLookupSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'query_address',
        'matched_address',
        'state',
        'district_number',
        'district_code',
        'resolved',
        'source',
        'error_message',
        'discovered_officials_count',
        'ip_address',
        'user_agent',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'resolved' => 'boolean',
            'discovered_officials_count' => 'integer',
            'payload' => 'array',
        ];
    }
}
