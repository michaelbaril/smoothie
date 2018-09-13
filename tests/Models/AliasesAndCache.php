<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Concerns\AliasesAttributesWithCache;
use Baril\Smoothie\Model;

class AliasesAndCache extends Model
{
    use AliasesAttributesWithCache;

    protected $fillable = [
        'cached_uniqid',
    ];

    protected $aliases = [
        'cached_uniqid_alias' => 'cached_uniqid',
    ];
    protected $uncacheable = ['uncached_uniqid'];
    protected $columnsPrefix = 'cached_';

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
