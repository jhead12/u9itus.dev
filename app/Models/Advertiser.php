<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advertiser extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'business_type',
        'website_url',
        'business_street',
        'business_city',
        'business_state',
        'business_zip',
        'business_country',
        'contact_name',
        'contact_phone',
        'contact_email',
        'stripe_customer_id',
        'paypal_merchant_id',
        'total_spent',
        'monthly_budget',
        'daily_budget',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'total_spent' => 'decimal:2',
            'monthly_budget' => 'decimal:2',
            'daily_budget' => 'decimal:2',
            'rating' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the advertiser profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all campaigns for this advertiser.
     */
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
}
