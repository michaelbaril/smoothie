<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\AliasesAndCache;
use Baril\Smoothie\Tests\Models\CacheAll;
use Baril\Smoothie\Tests\Models\CacheAllBut;
use Baril\Smoothie\Tests\Models\CacheOnly;

class AccessorCacheTest extends TestCase
{
    protected function setUp() : void
    {
    }

    /**
     * @dataProvider cachedProvider
     */
    public function test_cached_accessor($model)
    {
        $uniqid = $model->cached_uniqid;
        $this->assertEquals($uniqid, $model->cached_uniqid);
    }

    /**
     * @dataProvider uncachedProvider
     */
    public function test_uncached_accessor($model)
    {
        $uniqid = $model->uncached_uniqid;
        $this->assertNotEquals($uniqid, $model->uncached_uniqid);
    }

    /**
     * @dataProvider cachedProvider
     */
    public function test_clear_cache($model)
    {
        $uniqid = $model->cached_uniqid;
        $model->cached_uniqid = 'toto';
        $this->assertNotEquals($uniqid, $model->cached_uniqid);
    }

    /**
     * @dataProvider cachedProvider
     */
    public function test_cache_cleared_by_another_attribute($model)
    {
        $uniqid = $model->cached_uniqid;
        $model->some_other_attribute = 'toto';
        $this->assertNotEquals($uniqid, $model->cached_uniqid);
    }

    public function test_aliases()
    {
        $model = new AliasesAndCache(['cached_uniqid' => 'toto']);

        // Checking that aliases work properly and are cached:
        $uniqid = $model->cached_uniqid;
        $this->assertEquals($uniqid, $model->cached_uniqid_alias);
        $this->assertEquals($uniqid, $model->uniqid);

        // Setting the alias should clear the property's cache and the other
        // alias' cache:
        $uniqid = $model->cached_uniqid;
        $model->cached_uniqid_alias = 'toto';
        $this->assertNotEquals($uniqid, $model->cached_uniqid);
        $this->assertNotEquals($uniqid, $model->uniqid);

        // Same thing with the implicit alias:
        $uniqid = $model->cached_uniqid;
        $model->uniqid = 'toto';
        $this->assertNotEquals($uniqid, $model->cached_uniqid);
        $this->assertNotEquals($uniqid, $model->uniqid);

        // Settings the property should clear both aliases' cache:
        $uniqid = $model->cached_uniqid_alias;
        $model->cached_uniqid = 'toto';
        $this->assertNotEquals($uniqid, $model->cached_uniqid_alias);
        $this->assertNotEquals($uniqid, $model->uniqid);
    }

    public function cachedProvider()
    {
        return [
            'cache all' => [new CacheAll],
            'cache all but' => [new CacheAllBut],
            'cache only' => [new CacheOnly],
            'aliases and cache' => [new AliasesAndCache],
        ];
    }

    public function uncachedProvider()
    {
        return [
            'cache all but' => [new CacheAllBut],
            'cache only' => [new CacheOnly],
            'aliases and cache' => [new AliasesAndCache],
        ];
    }
}
