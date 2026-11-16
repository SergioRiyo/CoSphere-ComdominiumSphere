<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCharge extends Model
{
    use HasFactory;

    protected $fillable = ['unit_id', 'description', 'amount_cents', 'due_date', 'status', 'paid_at'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_at' => 'date', 'amount_cents' => 'integer'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(FinancialChargeHistory::class)->orderByDesc('id');
    }

    public function displayStatus(): string
    {
        return $this->status === 'open' && $this->due_date->lt(today()) ? 'overdue' : $this->status;
    }
}
