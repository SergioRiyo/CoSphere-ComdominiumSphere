<?php

namespace App\Models;

use App\Enums\VisitorAuthorizationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorAuthorization extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'visitor_authorizations';

    protected $fillable = [
        'visitor_id',
        'vehicle_plate',
        'invitation_expires_at',
        'invitation_used_at',
        'start_date',
        'end_date',
        'status',
        'authorized_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'authorized_date' => 'datetime',
        'invitation_expires_at' => 'datetime',
        'invitation_used_at' => 'datetime',
        'status' => VisitorAuthorizationStatus::class,
    ];

    protected $hidden = [
        'access_code',
        'invitation_token_hash',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function visitorAccesses(): HasMany
    {
        return $this->hasMany(VisitorAccess::class);
    }
}
