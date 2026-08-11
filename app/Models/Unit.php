<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'block',
        'number',
        'type',
        'complement',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function visitorAuthorizations(): HasMany
    {
        return $this->hasMany(VisitorAuthorization::class, 'unit_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
