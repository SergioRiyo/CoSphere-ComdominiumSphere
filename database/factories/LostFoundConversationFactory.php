<?php

namespace Database\Factories;

use App\Models\LostFoundConversation;
use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LostFoundConversation> */
class LostFoundConversationFactory extends Factory
{
    public function definition(): array
    {
        return ['lost_found_item_id' => LostFoundItem::factory(), 'resident_id' => User::factory()->morador()];
    }
}
