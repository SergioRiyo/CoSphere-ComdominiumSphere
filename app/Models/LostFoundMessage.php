<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostFoundMessage extends Model
{
    use HasFactory;

    protected $fillable = ['sender_id', 'body'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(LostFoundConversation::class, 'lost_found_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
