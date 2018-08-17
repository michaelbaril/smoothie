<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Status;

class MiscTest extends TestCase
{
    public function test_order_by_keys()
    {
        $models = factory(Status::class, 10)->create();
        $ids = $models->pluck('id')->shuffle()->all();
        $models = Status::all()->sortByKeys($ids);
        $this->assertEquals($ids, $models->pluck('id')->all());
    }
}
