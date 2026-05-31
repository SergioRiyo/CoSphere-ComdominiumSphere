<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommonArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'available_from',
        'available_until',
        'max_reservation_minutes',
        'rules',
        'is_active',
        'requires_approval',
    ];

    protected $attributes =
        [
            'is_active' => true,
            'requires_approval' => true,
        ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
