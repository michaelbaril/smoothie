<?php

namespace Baril\Smoothie\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $guarded = [];

    public function birthCountry()
    {
        return $this->belongsTo(Country::class)->usingCache();
    }

    public function citizenships()
    {
        return $this->belongsToMany(Country::class)->usingCache();
    }
}
