<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<IncidentAttachment> */
class IncidentAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'original_name' => 'foto.png',
            'path' => fn (array $attributes): string => 'incidents/'.$attributes['incident_id'].'/'.Str::uuid().'.png',
            'mime_type' => 'image/png',
            'size' => 100,
            'uploaded_by_user_id' => fn (array $attributes): int => Incident::withTrashed()->findOrFail($attributes['incident_id'])->resident_id,
            'uploaded_at' => now(),
        ];
    }
}
