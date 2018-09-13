<?php

namespace Baril\Smoothie\Tests\Models;

class CacheAllBut extends CacheAll
{
    protected $uncacheable = ['uncached_uniqid'];
}
