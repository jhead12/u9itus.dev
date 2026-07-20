<?php

namespace Database\Factories;

use App\Models\Citizen;
use App\Models\CitizenCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

class CitizenCreditFactory extends Factory
{
    protected $model = CitizenCredit::class;

    public function definition(): array
    {
        return [
            'citizen_id' => Citizen::factory(),
            'transaction_type' => 'purchase',
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'balance_after' => function (array $attributes) {
                return $attributes['amount'];
            },
            'citizen_campaign_id' => null,
            'related_transaction_id' => null,
            'description' => 'Test credit entry',
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}
