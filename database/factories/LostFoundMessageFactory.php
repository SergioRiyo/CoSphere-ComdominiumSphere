<?php

namespace Database\Factories;

use App\Models\LostFoundConversation;
use App\Models\LostFoundMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LostFoundMessage> */
class LostFoundMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['lost_found_conversation_id' => LostFoundConversation::factory(),
            'sender_id' => fn (array $attributes): int => LostFoundConversation::findOrFail($attributes['lost_found_conversation_id'])->resident_id,
            'body' => 'Acredito que este item seja meu.'];
    }
}
