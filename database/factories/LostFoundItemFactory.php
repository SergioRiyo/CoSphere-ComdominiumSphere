<?php

namespace Database\Factories;

use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LostFoundItem> */
class LostFoundItemFactory extends Factory
{
    public function definition(): array
    {
        return ['author_id' => User::factory()->morador(), 'type' => 'found', 'description' => 'Chave encontrada',
            'condition' => 'Bom estado', 'location' => 'Portaria', 'event_date' => today(), 'status' => 'open'];
    }
}
