<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PoliticianPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $fillable = [
        'politician_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'label',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
