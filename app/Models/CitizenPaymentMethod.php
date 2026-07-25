<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CitizenPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'citizen_payment_methods';

    protected $fillable = [
        'citizen_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'label',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function citizen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }
}
