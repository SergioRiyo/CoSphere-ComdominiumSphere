<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialChargeHistory extends Model
{
    protected $fillable = ['actor_id', 'action', 'before', 'after', 'reason'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(FinancialCharge::class, 'financial_charge_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
