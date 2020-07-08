<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Model;

class Post extends Model
{
    protected $casts = [
        'publication_date' => 'date',
    ];

    public function tags()
    {
        return $this->morphToManyOrdered(Tag::class, 'taggable', 'order');
    }
}
