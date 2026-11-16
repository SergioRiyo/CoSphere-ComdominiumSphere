<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LostFoundItem extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'description', 'condition', 'location', 'event_date', 'status', 'photo_path', 'photo_mime'];

    protected $hidden = ['photo_path', 'photo_mime'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LostFoundHistory::class)->orderByDesc('id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(LostFoundConversation::class);
    }
}
