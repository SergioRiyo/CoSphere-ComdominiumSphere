<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Order;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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

    public function units()
    {
        return $this->belongsToMany(User::class, 'user_unit')
            ->withPivot('classification', 'status')
            ->withTimestamps();
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
}
