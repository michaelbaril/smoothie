<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Post;
use Baril\Smoothie\Tests\Models\Tag as Model;
use Baril\Smoothie\Tests\Models\Video;

class MorphToManyOrderedTest extends BelongsToManyOrderedTest
{
    protected function setUp() : void
    {
        parent::setUp();
        $this->articles = factory(Post::class, 2)->create();
        $this->items = factory(Model::class, 8)->create();
        $video = factory(Video::class)->create();
        $video->tags()->sync($this->items);
    }
}
