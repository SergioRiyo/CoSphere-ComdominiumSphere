<?php

namespace Database\Factories;

use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommonAreaBlock> */
class CommonAreaBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'common_area_id' => CommonArea::factory(),
            'admin_id' => User::factory()->admin(),
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(16, 0),
            'reason' => 'Manutenção elétrica',
        ];
    }
}
