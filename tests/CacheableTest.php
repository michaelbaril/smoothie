<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Country;
use Baril\Smoothie\Tests\Models\User;

class CacheableTest extends TestCase
{
    protected $fixtures = [
        ['code' => 'FR', 'name' => 'France', 'continent' => 'Europe'],
        ['code' => 'IT', 'name' => 'Italia', 'continent' => 'Europe'],
        ['code' => 'US', 'name' => 'United States', 'continent' => 'America'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'continent' => 'Europe'],
        ['code' => 'DUM', 'name' => 'dummy country', 'continent' => 'who knows'],
    ];
    protected $queryCount = 0;

    protected function setUp() : void
    {
        parent::setUp();
        foreach ($this->fixtures as $data) {
            factory(Country::class)->create($data);
        }
        \DB::enableQueryLog();
        $this->resetQueryCount();
    }

    protected function resetQueryCount()
    {
        $this->queryCount = count(\DB::getQueryLog());
    }

    protected function assertQueryCount($num, $reset = false)
    {
        $this->assertEquals($num, count(\DB::getQueryLog()) - $this->queryCount);
        if ($reset) {
            $this->resetQueryCount();
        }
    }

    protected function assertCachedQuery($query, $count = null)
    {
        $uncachedQuery = (clone $query)->usingCache(false)->where('code', '!=', 'DUM');
        $cachedQuery = (clone $query)->usingCache();

        $uncachedResults = $uncachedQuery->get()->pluck('code')->toArray();
        $cachedResults = $cachedQuery->get()->pluck('code')->toArray();

        $this->assertEquals($uncachedResults, $cachedResults);
        if ($count !== null) {
            $this->assertCount($count, $cachedResults);
        }
    }

    public function test_all_from_database()
    {
        $all = Country::all();
        $this->assertCount(4, $all);
        $this->assertQueryCount(1);
    }

    public function test_static_methods_caching()
    {
        $country = Country::first();
        $this->assertNotNull(Country::find($country->id));
        $this->assertCount(4, Country::all());
        $this->assertNotNull(Country::pluck('code'));
        $this->assertEquals(4, Country::count());
        $this->assertQueryCount(1);
    }

    public function test_using_cache()
    {
        Country::all();
        $this->resetQueryCount();
        Country::where('code', '=', 'FR')->get();
        $this->assertQueryCount(1, true);
        $this->assertEquals('Italia', Country::where('code', '=', 'IT')->usingCache()->get()->first()->name);
        $this->assertQueryCount(0);
        Country::where('code', '=', 'IT')->usingCache()->usingCache(false)->get();
        $this->assertQueryCount(1, true);
    }

    public function test_cached_queries_results()
    {
        $this->assertCachedQuery(Country::orderBy('code')->orderBy('name'));
        $this->assertCachedQuery(Country::orderBy('continent')->orderBy('name'));
        $this->assertCachedQuery(Country::orderBy('name')->orderBy('continent'));
        $this->assertCachedQuery(Country::where('code', '!=', 'GB'), 3);
    }

    public function test_belongs_to_relation()
    {
        $person = factory(User::class)->create();

        $country = Country::orderBy('id', 'desc')->first();
        $person->birthCountry()->associate($country);
        $this->resetQueryCount();
        $this->assertEquals($country->id, $person->birthCountry->id);
        $this->assertQueryCount(0);

        $country = Country::orderBy('id', 'asc')->first();
        $person->birthCountry()->associate($country);
        $this->resetQueryCount();
        $this->assertEquals($country->id, $person->birthCountry->id);
        $this->assertQueryCount(0);
    }

    public function test_belongs_to_many_relation()
    {
        $person = factory(User::class)->create();
        $person->citizenships()->attach(Country::all()->slice(0, 2));
        $this->resetQueryCount();
        $this->assertCount(2, $person->citizenships);
        $this->assertQueryCount(1);
    }

    public function test_cache_refresh()
    {
        $this->assertCount(4, Country::all());
        Country::create(['code' => 'ES', 'name' => 'España', 'continent' => 'Europe']);
        $this->resetQueryCount();
        $this->assertCount(5, Country::all());
        $this->assertQueryCount(1);
        Country::first()->delete();
        $this->resetQueryCount();
        $this->assertCount(4, Country::all());
        $this->assertQueryCount(1);
        Country::first()->update(['name' => 'Some Random Country Name']);
        $this->resetQueryCount();
        $this->assertCount(4, Country::all());
        $this->assertQueryCount(1);
    }
}
