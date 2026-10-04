<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommonAreaBlock extends Model
{
    use HasFactory;

    protected $fillable = ['common_area_id', 'starts_at', 'ends_at', 'reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function commonArea(): BelongsTo
    {
        return $this->belongsTo(CommonArea::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** @param Builder<CommonAreaBlock> $query */
    #[Scope]
    protected function conflicting(Builder $query, CommonArea $area, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('common_area_id', $area->id)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
