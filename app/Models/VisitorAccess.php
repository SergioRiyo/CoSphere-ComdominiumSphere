<?php

namespace App\Models;

use App\Enums\VisitorAccessStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorAccess extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'visitor_accesses';

    protected $fillable = [
        'visitor_authorization_id',
        'doorman_id',
        'exit_doorman_id',
        'entry_time',
        'exit_time',
        'validation_status',
        'observations',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'validation_status' => VisitorAccessStatus::class,
    ];

    public function visitorAuthorization(): BelongsTo
    {
        return $this->belongsTo(VisitorAuthorization::class);
    }

    public function doorman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doorman_id');
    }

    public function exitDoorman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exit_doorman_id');
    }
}
