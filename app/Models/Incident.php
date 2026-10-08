<?php

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'resident_id', 'title', 'type', 'category', 'description',
        'opened_at', 'status', 'priority',
    ];

    protected $attributes = [
        'type' => IncidentType::Incident->value,
        'status' => IncidentStatus::Open->value,
        'priority' => IncidentPriority::Medium->value,
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'priority' => IncidentPriority::class,
        ];
    }

    /** Known categories are typed; unknown legacy values remain unchanged and readable. */
    protected function category(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): IncidentCategory|string|null => $value === null
                ? null : (IncidentCategory::tryFrom($value) ?? $value),
            set: fn (IncidentCategory|string $value): string => $value instanceof IncidentCategory ? $value->value : $value,
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class)->orderBy('uploaded_at')->orderBy('id');
    }

    public function priorityHistory(): HasMany
    {
        return $this->hasMany(IncidentPriorityHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
