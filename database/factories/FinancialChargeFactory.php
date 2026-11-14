<?php

namespace Database\Factories;

use App\Models\FinancialCharge;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialCharge> */
class FinancialChargeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'description' => 'Taxa condominial simulada',
            'amount_cents' => 25000,
            'due_date' => today()->addDays(10),
            'status' => 'open',
        ];
    }
}
