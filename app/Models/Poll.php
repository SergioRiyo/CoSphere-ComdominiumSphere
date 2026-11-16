<?php

namespace App\Models;

use App\Enums\PollStatus;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasFactory;

    protected $fillable = ['question'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function status(): PollStatus
    {
        if ($this->published_at === null) {
            return PollStatus::Draft;
        }
        if ($this->ends_at !== null && $this->ends_at->lessThanOrEqualTo(now())) {
            return PollStatus::Closed;
        }
        if ($this->published_at->isFuture() || $this->starts_at === null || $this->starts_at->isFuture()) {
            return PollStatus::Scheduled;
        }

        return PollStatus::Open;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }
}
