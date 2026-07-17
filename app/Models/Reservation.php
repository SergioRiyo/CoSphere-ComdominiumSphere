<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'common_area_id',
        'user_id',
        'unit_id',
        'starts_at',
        'ends_at',
        'status',
        'rejection_reason',
    ];

    protected $attributes = [
        'status' => 'confirmed',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ReservationStatus::class,
        ];
    }

    public function commonArea(): BelongsTo
    {
        return $this->belongsTo(CommonArea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
