<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Model;

class Tag extends Model
{
    use \Baril\Smoothie\Concerns\BelongsToOrderedTree;

    protected $fillable = ['name'];

    public function related()
    {
        return $this->mutuallyBelongsToManySelves('tag_relations', 'tag_id', 'other_tag_id');
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class);
    }

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function videos()
    {
        return $this->morphedByMany(Video::class, 'taggable');
    }
}
