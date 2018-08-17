<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Model;
use Baril\Smoothie\Tests\Models\Status;
use Baril\Smoothie\Tests\Models\Tag;

class Article extends Model
{
    protected $fillable = ['title', 'body', 'status_id', 'publication_date'];

    public function tags()
    {
        return $this->belongsToManyOrdered(Tag::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function getPublicationDateAttribute()
    {
        return $this->mergeDate('publication_date');
    }

    public function setPublicationDateAttribute($value)
    {
        $this->splitDate($value, 'publication_date');
    }
}
