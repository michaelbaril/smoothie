<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\PositionException;
use Baril\Smoothie\Tests\Models\Article;
use Baril\Smoothie\Tests\Models\Tag as Model;

class BelongsToManyOrderedTest extends TestCase
{
    protected $articles;
    protected $items;

    protected function setUp() : void
    {
        parent::setUp();
        $this->articles = factory(Article::class, 2)->create();
        $this->items = factory(Model::class, 8)->create();
    }

    protected function syncTags($article, $tags)
    {
        $tagIds = [];
        foreach ($tags as $i) {
            $tagIds[] = $this->items[$i]->id;
        }
        return $this->articles[$article]->tags()->sync($tagIds);
    }

    protected function assertTagsForArticle($expected, $article)
    {
        $orderColumn = $this->articles[$article]->tags()->getOrderColumn();
        $actual = $this->articles[$article]->tags()->get();
        foreach ($expected as $i => $e) {
            $this->assertEquals($this->items[$e]->id, $actual[$i]->id);
            $this->assertEquals($i + 1, $actual[$i]->pivot->$orderColumn);
        }
    }

    public function test_relation_name()
    {
        $this->assertEquals('tags', $this->articles[0]->tags()->getRelationName());
    }

    public function test_position_on_attach()
    {
        $article = $this->articles[0];
        $article->tags()->attach($this->items[0]);
        $article->tags()->attach($this->items[1]);
        $this->assertTagsForArticle([0, 1], 0);
    }

    public function test_positions_on_detach()
    {
        $article = $this->articles[0];
        $article->tags()->attach($this->items[0]);
        $article->tags()->attach($this->items[1]);
        $article->tags()->attach($this->items[2]);
        $article->tags()->detach($this->items[0]);
        $this->assertTagsForArticle([1, 2], 0);
    }

    public function test_positions_on_sync()
    {
        $this->syncTags(0, [3, 5, 1]);
        $this->assertTagsForArticle([3, 5, 1], 0);
        $this->syncTags(0, [1, 4, 5]);
        $this->assertTagsForArticle([1, 4, 5], 0);
        $this->syncTags(0, [6, 7]);
        $this->assertTagsForArticle([6, 7], 0);
    }

    public function test_move()
    {
        $article = $this->articles[1];
        $this->syncTags(1, [0, 1, 2, 3, 4]);
        $this->assertTagsForArticle([0, 1, 2, 3, 4], 1);

        $article->tags()->moveToOffset($this->items[1], -2);
        $this->assertTagsForArticle([0, 2, 3, 1, 4], 1);

        $article->tags()->moveToStart($this->items[2]);
        $this->assertTagsForArticle([2, 0, 3, 1, 4], 1);

        $article->tags()->moveToEnd($this->items[3]);
        $this->assertTagsForArticle([2, 0, 1, 4, 3], 1);

        $article->tags()->moveToPosition($this->items[4], 3);
        $this->assertTagsForArticle([2, 0, 4, 1, 3], 1);

        $article->tags()->moveToPosition($this->items[0], 4);
        $this->assertTagsForArticle([2, 4, 1, 0, 3], 1);

        $article->tags()->swap($this->items[1], $this->items[3]);
        $this->assertTagsForArticle([2, 4, 3, 0, 1], 1);

        $article->tags()->moveBefore($this->items[2], $this->items[0]);
        $this->assertTagsForArticle([4, 3, 2, 0, 1], 1);

        $article->tags()->moveAfter($this->items[3], $this->items[1]);
        $this->assertTagsForArticle([4, 2, 0, 1, 3], 1);

        $article->tags()->moveBefore($this->items[3], $this->items[1]);
        $this->assertTagsForArticle([4, 2, 0, 3, 1], 1);

        $article->tags()->moveAfter($this->items[3], $this->items[4]);
        $this->assertTagsForArticle([4, 3, 2, 0, 1], 1);

        $article->tags()->moveUp($this->items[0], 2);
        $this->assertTagsForArticle([4, 0, 3, 2, 1], 1);

        $article->tags()->moveDown($this->items[3], 12345, false);
        $this->assertTagsForArticle([4, 0, 2, 1, 3], 1);
    }

    public function test_move_to_invalid_position()
    {
        $article = $this->articles[1];
        $this->syncTags(1, [0, 1, 2, 3, 4]);
        $this->expectException(PositionException::class);
        $article->tags()->moveToPosition($this->items[0], 7);
    }

    public function test_ordered_and_unordered_scopes()
    {
        $article = $this->articles[1];
        $this->syncTags(1, [5, 1, 2, 4, 3]);

        $expected = [
            $this->items[3]->id,
            $this->items[4]->id,
            $this->items[2]->id,
            $this->items[1]->id,
            $this->items[5]->id,
        ];
        $actual = $article->tags()->ordered('desc')->pluck('id')->toArray();
        $this->assertEquals($expected, $actual);

        sort($expected);$actual = $article->tags()->unordered()->orderBy('id')->pluck('id')->toArray();
        $this->assertEquals($expected, $actual);
    }

    public function test_before_and_after()
    {
        $article = $this->articles[0];
        $this->syncTags(0, [5, 1, 2, 4, 3]);

        $this->assertEquals(2, $article->tags()->before($this->items[2])->count());
        $this->assertEquals(3, $article->tags()->after($this->items[1])->count());
    }

    public function test_set_order()
    {
        $article = $this->articles[0];
        $this->syncTags(0, [5, 1, 2, 4, 3]);
        $article->tags()->setOrder([
            $this->items[3]->id,
            $this->items[2]->id,
            $this->items[1]->id,
        ]);
        $this->assertTagsForArticle([3, 2, 1, 5, 4], 0);
    }
}
