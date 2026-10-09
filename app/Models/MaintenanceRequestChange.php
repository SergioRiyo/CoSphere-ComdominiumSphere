<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequestChange extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['changes', 'actor_role'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'actor_role' => UserRole::class, 'created_at' => 'immutable_datetime'];
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class)->withTrashed();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
