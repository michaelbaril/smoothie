<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Concerns\CachesAccessors;
use Baril\Smoothie\Model;

class CacheAll extends Model
{
    use CachesAccessors;

    protected $clearAccessorCache = [
        'some_other_attribute' => ['cached_uniqid'],
    ];

    public function getUncachedUniqidAttribute()
    {
        return uniqid();
    }

    public function getCachedUniqidAttribute()
    {
        return uniqid();
    }

    public function setSomeOtherAttributeAttribute($value)
    {

    }
}
