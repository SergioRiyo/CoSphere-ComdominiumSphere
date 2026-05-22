<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [];


    public function users() {}

    public function visitorAuthorizations()
    {
        return $this->hasMany(VisitorAuthorization::class, 'unit_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
