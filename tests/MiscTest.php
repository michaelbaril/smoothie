<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Article;
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

    public function test_update_only()
    {
        $article = factory(Article::class)->create(['title' => 'old title', 'body' => 'old body']);
        $article->body = 'new body';
        $this->assertTrue($article->updateOnly(['title' => 'new title']));
        $this->assertEquals('new title', $article->title);
        $this->assertEquals('old body', $article->fresh()->body);
    }
}
