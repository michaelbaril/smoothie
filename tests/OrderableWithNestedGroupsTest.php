<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\PositionException;
use Baril\Smoothie\Tests\Models\Article;
use Baril\Smoothie\Tests\Models\Paragraph as Model;

class OrderableWithNestedGroupsTest extends TestCase
{
    protected $articles;
    protected $items;

    protected function setUp() : void
    {
        parent::setUp();
        $this->articles = factory(Article::class, 2)->create();
        $this->items = factory(Model::class, 8)->create([
            'article_id' => $this->articles[0]->id,
            'section' => 1,
        ]);
    }

    protected function setGroup($items, $article, $section)
    {
        foreach ((array) $items as $item) {
            $this->items[$item] = $this->items[$item]->fresh();
            $this->items[$item]->article()->associate($this->articles[$article]);
            $this->items[$item]->section = $section;
            $this->items[$item]->save();
        }
    }

    protected function assertPositionsWithinGroup($expected, $article, $section)
    {
        $group = [
            $this->items[$article]->id,
            $section
        ];

        $actual = Model::inGroup($group)->orderBy('id')->pluck('position')->toArray();
        $this->assertEquals($expected, $actual);
    }

    public function test_positions_on_group_change()
    {
        $this->setGroup(1, 0, 2);
        $this->items[1]->save();
        $this->assertEquals(1, $this->items[1]->position);
        $this->assertPositionsWithinGroup([1, 2, 3, 4, 5, 6, 7], 0, 1);
    }

    public function test_position_on_create()
    {
        $this->setGroup(1, 0, 2);
        $this->items[1]->save();
        $model = factory(Model::class)->make(['article_id' => $this->articles[0]->id, 'section' => 2]);
        $model->save();
        $this->assertEquals(2, $model->position);
    }

    public function test_positions_on_delete()
    {
        $this->setGroup([1, 2, 3], 0, 2);
        $this->items[2]->delete();
        $this->assertPositionsWithinGroup([1, 2], 0, 2);
    }

    public function test_move()
    {
        $this->setGroup([0, 1, 2, 3, 4], 1, 1);
        $this->assertPositionsWithinGroup([1, 2, 3, 4, 5], 1, 1);
        $this->items[1]->fresh()->moveToOffset(-2);
        $this->assertPositionsWithinGroup([1, 4, 2, 3, 5], 1, 1);
        $this->items[2]->fresh()->moveToStart();
        $this->assertPositionsWithinGroup([2, 4, 1, 3, 5], 1, 1);
        $this->items[3]->fresh()->moveToEnd();
        $this->assertPositionsWithinGroup([2, 3, 1, 5, 4], 1, 1);
        $this->items[4]->fresh()->moveToPosition(3);
        $this->assertPositionsWithinGroup([2, 4, 1, 5, 3], 1, 1);
        $this->items[0]->fresh()->moveToPosition(4);
        $this->assertPositionsWithinGroup([4, 3, 1, 5, 2], 1, 1);
        $this->items[1]->fresh()->swapWith($this->items[3]->fresh());
        $this->assertPositionsWithinGroup([4, 5, 1, 3, 2], 1, 1);
        $this->items[2]->fresh()->moveBefore($this->items[0]->fresh());
        $this->assertPositionsWithinGroup([4, 5, 3, 2, 1], 1, 1);
        $this->items[3]->fresh()->moveAfter($this->items[1]->fresh());
        $this->assertPositionsWithinGroup([3, 4, 2, 5, 1], 1, 1);
        $this->items[3]->fresh()->moveBefore($this->items[1]->fresh());
        $this->assertPositionsWithinGroup([3, 5, 2, 4, 1], 1, 1);
        $this->items[3]->fresh()->moveAfter($this->items[4]->fresh());
        $this->assertPositionsWithinGroup([4, 5, 3, 2, 1], 1, 1);
    }

    public function test_move_to_invalid_position()
    {
        $this->setGroup([0, 1, 2, 3, 4], 1, 1);
        $this->expectException(PositionException::class);
        $this->items[0]->moveToPosition(7);
    }
}
