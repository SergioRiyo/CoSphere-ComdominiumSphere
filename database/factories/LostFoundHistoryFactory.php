<?php

namespace Database\Factories;

use App\Models\LostFoundHistory;
use App\Models\LostFoundItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LostFoundHistory> */
class LostFoundHistoryFactory extends Factory
{
    public function definition(): array
    {
        return ['lost_found_item_id' => LostFoundItem::factory(),
            'actor_id' => fn (array $attributes): int => LostFoundItem::findOrFail($attributes['lost_found_item_id'])->author_id,
            'action' => 'created', 'after' => ['status' => 'open']];
    }
}
