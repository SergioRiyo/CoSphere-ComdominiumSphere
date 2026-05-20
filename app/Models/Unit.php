<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
      protected $fillable = [];


      public function users(){
        
      }

      public function visitorAuthorizations()
      {
          return $this->hasMany(VisitorAuthorization::class, 'unit_id');
      }
}
