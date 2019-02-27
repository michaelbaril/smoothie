<?php

namespace Baril\Smoothie\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Baril\Smoothie\Concerns\Cacheable;

class Country extends Model
{
    use Cacheable;

    protected $fillable = ['code', 'name', 'continent'];
    protected $cache = 'array';

    protected static function loadFromDatabase()
    {
        return static::where('code', '!=', 'DUM')->get();
    }
}
