<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Model;

class Post extends Model
{
    public function tags()
    {
        return $this->morphToManyOrdered(Tag::class, 'taggable', 'order');
    }
}
