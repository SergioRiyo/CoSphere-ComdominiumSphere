<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentAttachment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['original_name', 'path', 'mime_type', 'size'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'uploaded_at' => 'immutable_datetime'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class)->withTrashed();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
