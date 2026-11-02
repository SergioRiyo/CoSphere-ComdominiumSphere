<?php

namespace App\Models;

use Database\Factories\PollVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PollVote extends Model
{
    /** @use HasFactory<PollVoteFactory> */
    use HasFactory;

    protected $fillable = ['poll_id', 'poll_option_id', 'user_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Votos não podem ser alterados.'));
        static::deleting(fn () => throw new LogicException('Votos não podem ser excluídos.'));
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
