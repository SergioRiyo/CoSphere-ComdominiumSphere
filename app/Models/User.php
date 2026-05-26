<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['unit_id', 'name', 'email', 'role', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $attributes = [
        'role' => 'morador',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [

            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function visitorAuthorizations()
    {
        return $this->hasMany(VisitorAuthorization::class, 'resident_id');
    }

    public function visitorAccesses()
    {
        return $this->hasMany(VisitorAccess::class, 'doorman_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'resident_id');
    }

    public function receivedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'received_by_id');
    }

    public function pickedUpOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'picked_up_by_id');
    }

    public function reportedIncidents()
    {
        return $this->hasMany(Incident::class, 'resident_id');
    }

    public function managedMaintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'admin_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }
}
