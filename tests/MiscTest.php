<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Status;
use Baril\Smoothie\Tests\Models\Tag;

class MiscTest extends TestCase
{
    public function test_order_by_keys()
    {
        $models = factory(Status::class, 10)->create();
        $ids = $models->pluck('id')->shuffle()->all();
        $models = Status::all()->sortByKeys($ids);
        $this->assertEquals($ids, $models->pluck('id')->all());
    }

    public function test_fresh_and_save()
    {
        $model = factory(Tag::class)->create(['name' => 'toto']);
        $sameModel = Tag::find($model->id);

        $model->name = 'titi';
        $model->save();
        $this->assertEquals('titi', $model->fresh()->name);

        $sameModel->save(['restore' => true]);
        $this->assertEquals('toto', $model->fresh()->name);
    }
}
