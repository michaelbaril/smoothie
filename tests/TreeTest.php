<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Tag;
use Baril\Smoothie\TreeException;

class TreeTest extends TestCase
{
    protected $tags;

    protected function setUp() : void
    {
        parent::setUp();
        $this->tags = collect([]);
        $this->tags['A'] = factory(Tag::class)->create();
        $this->tags['AA'] = factory(Tag::class)->create(['parent_id' => $this->tags['A']->id]);
        $this->tags['AB'] = factory(Tag::class)->create(['parent_id' => $this->tags['A']->id]);
        $this->tags['ABA'] = factory(Tag::class)->create(['parent_id' => $this->tags['AB']->id]);
        $this->tags['B'] = factory(Tag::class)->create();
    }

    public function test_relations()
    {
        // parent
        $this->assertEquals($this->tags['A']->id, $this->tags['AB']->parent->id);

        // children
        $expected = [
            $this->tags['AA']->id,
            $this->tags['AB']->id,
        ];
        $this->assertEquals($expected, $this->tags['A']->children->pluck('id')->toArray());

        // descendants
        $expected[] = $this->tags['ABA']->id;
        $this->assertEquals($expected, $this->tags['A']->descendants()->orderByDepth()->pluck('id')->toArray());

        // descendantsWithSelf
        array_unshift($expected, $this->tags['A']->id);
        $this->assertEquals($expected, $this->tags['A']->descendantsWithSelf()->orderByDepth()->pluck('id')->toArray());

        // ancestors
        $expected = [
            $this->tags['AB']->id,
            $this->tags['A']->id,
        ];
        $this->assertEquals($expected, $this->tags['ABA']->ancestors()->orderByDepth()->pluck('id')->toArray());

        // ancestorsWithSelf
        array_unshift($expected, $this->tags['ABA']->id);
        $this->assertEquals($expected, $this->tags['ABA']->ancestorsWithSelf()->orderByDepth()->pluck('id')->toArray());
    }

    /**
     * @dataProvider redundancyProvider
     */
    public function test_redundancy($tag)
    {
        $this->expectException(TreeException::class);
        $this->tags['A']->parent_id = $this->tags[$tag]->id;
        $this->tags['A']->save();
    }

    public function redundancyProvider()
    {
        return [
            'A' => ['A'],
            'AB' => ['AB'],
            'ABA' => ['ABA'],
        ];
    }

    public function test_methods()
    {
        $this->assertTrue($this->tags['B']->isRoot());
        $this->assertFalse($this->tags['AA']->isRoot());
        $this->assertTrue($this->tags['AA']->isLeaf());
        $this->assertFalse($this->tags['AB']->isLeaf());
        $this->assertTrue($this->tags['ABA']->isChildOf($this->tags['AB']));
        $this->assertTrue($this->tags['ABA']->isDescendantOf($this->tags['A']));
        $this->assertTrue($this->tags['AB']->isAncestorOf($this->tags['ABA']));
        $this->assertTrue($this->tags['AB']->isSiblingOf($this->tags['AA']));
        $this->assertFalse($this->tags['ABA']->isSiblingOf($this->tags['AA']));
        $this->assertFalse($this->tags['ABA']->isChildOf($this->tags['AA']));
    }

    public function test_common_ancestor()
    {
        $this->assertNull($this->tags['A']->commonAncestorWith($this->tags['B']));
        $this->assertEquals($this->tags['A']->id, $this->tags['ABA']->commonAncestorWith($this->tags['AA'])->id);
        $this->assertEquals($this->tags['A']->id, $this->tags['ABA']->commonAncestorWith($this->tags['A'])->id);
        $this->assertEquals($this->tags['A']->id, $this->tags['A']->commonAncestorWith($this->tags['AA'])->id);
    }

    public function test_distance_exception()
    {
        $this->expectException(TreeException::class);
        $this->tags['A']->distanceTo($this->tags['B']);
    }

    public function test_distance_and_depth()
    {
        $this->assertEquals(0, $this->tags['AB']->distanceTo($this->tags['AB']));
        $this->assertEquals(2, $this->tags['ABA']->distanceTo($this->tags['A']));
        $this->assertEquals(3, $this->tags['AA']->distanceTo($this->tags['ABA']));
        $this->assertEquals(0, $this->tags['A']->depth());
        $this->assertEquals(2, $this->tags['ABA']->depth());
        $this->assertEquals(2, $this->tags['A']->subtreeDepth());
        $this->assertEquals(1, $this->tags['AB']->subtreeDepth());
        $this->assertEquals(0, $this->tags['ABA']->subtreeDepth());
    }

    public function test_scopes()
    {
        $this->assertEquals(2, Tag::whereIsRoot()->count());
        $this->assertEquals(3, Tag::whereIsRoot(false)->count());
        $this->assertEquals(3, Tag::whereIsLeaf()->count());
        $this->assertEquals(3, Tag::whereIsDescendantOf($this->tags['A']->id)->count());
        $this->assertEquals(2, Tag::whereIsDescendantOf($this->tags['A']->id, 1)->count());
        $this->assertEquals(3, Tag::whereIsDescendantOf($this->tags['A']->id, 1, true)->count());
        $this->assertEquals(2, Tag::whereIsAncestorOf($this->tags['ABA']->id)->count());
        $this->assertEquals(1, Tag::whereIsAncestorOf($this->tags['ABA']->id, 1)->count());
        $this->assertEquals(2, Tag::whereIsAncestorOf($this->tags['ABA']->id, 1, true)->count());
    }

    public function test_with_descendants()
    {
        $tags = Tag::with('descendants')->whereKey($this->tags['A']->id)->get();
        \DB::enableQueryLog();
        $count = count(\DB::getQueryLog());
        $this->assertCount(3, $tags[0]->descendants);
        $this->assertCount(2, $tags[0]->children);
        $this->assertCount(1, $tags[0]->children[1]->children);
        $this->assertCount(0, $tags[0]->children[1]->children[0]->children);
        $this->assertEquals($count, count(\DB::getQueryLog())); // checking that no new query has been necessary
    }

    public function test_with_descendants_and_limited_depth()
    {
        $tags = Tag::withDescendants(1)->whereKey($this->tags['A']->id)->get();
        $this->assertCount(2, $tags[0]->descendants);
        $this->assertTrue($tags[0]->relationLoaded('children'));
        $this->assertFalse($tags[0]->children[1]->relationLoaded('children'));
    }

    public function test_with_ancestors()
    {
        $tags = Tag::with('ancestors')->whereKey($this->tags['ABA']->id)->get();
        \DB::enableQueryLog();
        $count = count(\DB::getQueryLog());
        $this->assertCount(2, $tags[0]->ancestors);
        $this->assertEquals($this->tags['A']->id, $tags[0]->parent->parent->id);
        $this->assertNull($tags[0]->parent->parent->parent);
        $this->assertEquals($count, count(\DB::getQueryLog())); // checking that no new query has been necessary
    }

    public function test_with_ancestors_and_limited_depth()
    {
        $tags = Tag::withAncestors(1)->whereKey($this->tags['ABA']->id)->get();
        $this->assertCount(1, $tags[0]->ancestors);
        $this->assertTrue($tags[0]->relationLoaded('parent'));
        $this->assertFalse($tags[0]->parent->relationLoaded('parent'));
    }

    public function test_with_depth()
    {
        $tags = Tag::withDepth()->get()->pluck('depth', 'id');
        $this->assertEquals(2, $tags[$this->tags['ABA']->id]);
        $tags = Tag::withDepth('alias')->get()->pluck('alias', 'id');
        $this->assertEquals(1, $tags[$this->tags['AA']->id]);
    }

    public function test_order_by_depth()
    {
        $this->tags['AA']->parent()->associate($this->tags['ABA'])->save(); // AA's parent is now ABA

        $ancestorsByDepth = $this->tags['AA']->ancestors()->orderByDepth()->pluck('id')->toArray();
        $expected = [
            $this->tags['ABA']->id,
            $this->tags['AB']->id,
            $this->tags['A']->id,
        ];
        $this->assertEquals($expected, $ancestorsByDepth);

        $tag = Tag::with(['descendants' => function ($query) {
            $query->orderByDepth('desc');
        }])->whereKey($this->tags['A']->id)->first();
        $descendantsByDepthDesc = $tag->descendants->pluck('id')->toArray();
        $expected = [
            $this->tags['AA']->id,
            $this->tags['ABA']->id,
            $this->tags['AB']->id,
        ];
        $this->assertEquals($expected, $descendantsByDepthDesc);
    }

    public function test_with_count()
    {
        $tags = Tag::withCount('descendants')->whereKey($this->tags['A']->id)->get();
        $this->assertEquals(3, $tags[0]->descendants_count);
        $tags = Tag::withCount('ancestors')->whereKey($this->tags['ABA']->id)->get();
        $this->assertEquals(2, $tags[0]->ancestors_count);
    }

    public function test_position()
    {
        $this->tags['AB']->moveToPosition(1);
        $this->assertEquals(1, Tag::find($this->tags['AB']->id)->position);
        $this->assertEquals(2, Tag::find($this->tags['AA']->id)->position);

        $expected = [
            $this->tags['AB']->id,
            $this->tags['AA']->id,
        ];
        $this->assertEquals($expected, $this->tags['A']->children()->ordered()->pluck('id')->toArray());
    }

    public function test_delete_failure()
    {
        $this->expectException(TreeException::class);
        $this->tags['AB']->delete();
    }

    public function test_delete_success()
    {
        $this->tags['A']->deleteTree();
        $this->assertNull(Tag::find($this->tags['A']->id));
        $this->assertNull(Tag::find($this->tags['AB']->id));
        $this->assertNull(Tag::find($this->tags['ABA']->id));
        $this->tags['B']->delete();
        $this->assertNull(Tag::find($this->tags['B']->id));
    }

    public function test_recreate_closures()
    {
        foreach ($this->tags as &$tag) {
            $tag->descendants_count = $tag->descendants()->count();
        }

        $closureTable = $this->tags['A']->getConnection()->table($this->tags['A']->getClosureTable());
        $count = $closureTable->count();
        // Inserting some bogus closure that should be deleted after the command has run:
        $closureTable->insert([
            ['ancestor_id' => $this->tags['A']->id, 'descendant_id' => $this->tags['B']->id, 'depth' => 1],
        ]);
        // Removing some legit closures:
        $closureTable->where('depth', 2)->delete();

        $this->artisan('smoothie:fix-tree', ['model' => Tag::class]);

        $this->assertEquals($count, $closureTable->cloneWithout(['wheres'])->count());
        foreach ($this->tags as $tag) {
            $this->assertEquals($tag->descendants_count, $tag->descendants()->count());
        }
    }
}
