<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Model;
use Baril\Smoothie\Tests\Models\Status;
use Baril\Smoothie\Tests\Models\Tag;

class Article extends Model
{
    protected $fillable = ['title', 'body', 'status_id'];

    public function tags()
    {
        return $this->belongsToManyOrdered(Tag::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
