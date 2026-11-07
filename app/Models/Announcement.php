<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'content'];

    protected static function booted(): void
    {
        static::deleting(function (Announcement $announcement): void {
            if ($announcement->published_at !== null || static::withTrashed()->whereKey($announcement->getKey())->whereNotNull('published_at')->exists()) {
                throw new LogicException('Comunicados publicados não podem ser excluídos.');
            }
        });
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
