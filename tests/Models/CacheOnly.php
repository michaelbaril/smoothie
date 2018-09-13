<?php

namespace Baril\Smoothie\Tests\Models;

class CacheOnly extends CacheAll
{
    protected $cacheable = ['cached_uniqid'];
}
