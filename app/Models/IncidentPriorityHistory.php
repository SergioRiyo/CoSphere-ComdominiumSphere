<?php

namespace App\Models;

use App\Enums\IncidentPriority;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentPriorityHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['from_priority', 'to_priority', 'actor_role'];

    protected function casts(): array
    {
        return [
            'from_priority' => IncidentPriority::class,
            'to_priority' => IncidentPriority::class,
            'actor_role' => UserRole::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class)->withTrashed();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
