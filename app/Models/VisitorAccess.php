<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorAccess extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'visitor_accesses';

    protected $fillable = [
        'visitor_authorization_id',
        'doorman_id',
        'entry_time',
        'exit_time',
        'validation_status',
        'observations',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
    ];

    public function visitorAuthorization()
    {
        return $this->belongsTo(VisitorAuthorization::class);
    }

    public function doorman()
    {
        return $this->belongsTo(User::class, 'doorman_id');
    }

}
