<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorAuthorization extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'visitor_authorizations';

    protected $fillable = [
        'visitor_id',
        'unit_id',
        'resident_id',
        'vehicle_plate',
        'access_code',
        'qr_code',
        'registration_link',
        'start_date',
        'end_date',
        'status',
        'authorized_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'authorized_date' => 'datetime',
    ];

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident()
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function visitorAccesses()
    {
        return $this->hasMany(VisitorAccess::class);
    }
    
}
