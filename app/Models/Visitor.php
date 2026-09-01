<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'visitors';

    protected $fillable = [
        'name',
        'cpf',
        'phone',
    ];

    public function authorizations(): HasMany
    {
        return $this->hasMany(VisitorAuthorization::class);
    }
}
