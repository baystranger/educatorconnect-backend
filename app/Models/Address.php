<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
     protected $fillable = [
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country',
        'latitude',
        'longitude',
    ];

    public function addressable(){return $this->morphTo();}
}
