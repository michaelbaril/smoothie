<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\PositionException;
use Baril\Smoothie\Tests\Models\Tag as Model;

class OrderedTreeTest extends TestCase
{
    protected $items;

    protected function setUp() : void
    {
        parent::setUp();
        $this->items = factory(Model::class, 8)->create();
    }

    protected function setParent($items, $parent)
    {
        foreach ((array) $items as $item) {
            $this->items[$item] = $this->items[$item]->fresh();
            $this->items[$item]->parent()->associate($this->items[$parent]);
            $this->items[$item]->save();
        }
    }

    protected function assertPositionsWithinGroup($expected, $group)
    {
        $actual = Model::inGroup($group)->orderBy('id')->pluck('position')->toArray();
        $this->assertEquals($expected, $actual);
    }

    protected function assertPositionsForChildren($expected, $parent)
    {
        $this->assertPositionsWithinGroup($expected, $this->items[$parent]->id);
    }

    public function test_positions_on_parent_change()
    {
        $this->setParent(1, 0);
        $this->items[1]->save();
        $this->assertEquals(1, $this->items[1]->position);
        $this->assertPositionsWithinGroup([1, 2, 3, 4, 5, 6, 7], null);
    }

    public function test_position_on_create()
    {
        $this->setParent(1, 0);
        $this->items[1]->save();
        $model = factory(Model::class)->make();
        $model->parent()->associate($this->items[0]);
        $model->save();
        $this->assertEquals(2, $model->position);
    }

    public function test_positions_on_delete()
    {
        $this->setParent([1, 2, 3], 0);
        $this->items[2]->delete();
        $this->assertPositionsForChildren([1, 2], 0);
    }

    public function test_move()
    {
        $this->setParent([0, 1, 2, 3, 4], 6);
        $this->assertPositionsForChildren([1, 2, 3, 4, 5], 6);
        $this->items[1]->fresh()->moveToOffset(-2);
        $this->assertPositionsForChildren([1, 4, 2, 3, 5], 6);
        $this->items[2]->fresh()->moveToStart();
        $this->assertPositionsForChildren([2, 4, 1, 3, 5], 6);
        $this->items[3]->fresh()->moveToEnd();
        $this->assertPositionsForChildren([2, 3, 1, 5, 4], 6);
        $this->items[4]->fresh()->moveToPosition(3);
        $this->assertPositionsForChildren([2, 4, 1, 5, 3], 6);
        $this->items[0]->fresh()->moveToPosition(4);
        $this->assertPositionsForChildren([4, 3, 1, 5, 2], 6);
        $this->items[1]->fresh()->swapWith($this->items[3]->fresh());
        $this->assertPositionsForChildren([4, 5, 1, 3, 2], 6);
        $this->items[2]->fresh()->moveBefore($this->items[0]->fresh());
        $this->assertPositionsForChildren([4, 5, 3, 2, 1], 6);
        $this->items[3]->fresh()->moveAfter($this->items[1]->fresh());
        $this->assertPositionsForChildren([3, 4, 2, 5, 1], 6);
        $this->items[3]->fresh()->moveBefore($this->items[1]->fresh());
        $this->assertPositionsForChildren([3, 5, 2, 4, 1], 6);
        $this->items[3]->fresh()->moveAfter($this->items[4]->fresh());
        $this->assertPositionsForChildren([4, 5, 3, 2, 1], 6);
    }

    public function test_move_to_invalid_position()
    {
        $this->setParent([0, 1, 2, 3, 4], 6);
        $this->expectException(PositionException::class);
        $this->items[0]->moveToPosition(7);
    }

    public function test_ordered_descendants()
    {
        // 0     3
        // 5 4   2 7
        //         6 1

        $this->setParent([5, 4], 0);
        $this->setParent([2, 7], 3);
        $this->setParent([6, 1], 7);

        $items = Model::with('descendants')->where('parent_id', null)->orderBy('id')->get();
        $this->assertEquals($this->items[5]->id, $items[0]->children[0]->id);
        $this->assertEquals($this->items[4]->id, $items[0]->children[1]->id);
        $this->assertEquals($this->items[2]->id, $items[1]->children[0]->id);
        $this->assertEquals($this->items[7]->id, $items[1]->children[1]->id);
        $this->assertEquals($this->items[6]->id, $items[1]->children[1]->children[0]->id);
        $this->assertEquals($this->items[1]->id, $items[1]->children[1]->children[1]->id);
    }
}
