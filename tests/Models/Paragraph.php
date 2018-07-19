<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Model;
use Baril\Smoothie\Tests\Models\Article;

class Paragraph extends Model
{
    use \Baril\Smoothie\Concerns\Orderable;

    protected $groupColumn = ['article_id', 'section'];

    protected $fillable = ['article_id', 'section', 'content'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
