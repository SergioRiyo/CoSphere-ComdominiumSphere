<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'unit_id',
        'resident_id',
        'received_by_id',
        'picked_up_by_id',
        'tracking_code',
        'sender',
        'carrier',
        'description',
        'expected_delivery_date',
        'received_at',
        'picked_up_at',
        'status',
    ];

    protected $casts = [
        'expected_delivery_date' => 'date',
        'received_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'status' => OrderStatus::class,
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by_id');
    }
}
