<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Tag;

class MutualSelfRelationshipTest extends TestCase
{
    protected $tags;

    protected function setUp() : void
    {
        parent::setUp();
        $this->tags = factory(Tag::class, 5)->create();
    }

    public function test_relation_name()
    {
        $this->assertEquals('related', $this->tags[0]->related()->getRelationName());
    }

    public function test_attach_and_detach()
    {
        $this->tags[0]->related()->attach($this->tags[1]);
        $this->assertContains($this->tags[1]->id, $this->tags[0]->related()->pluck('id'));
        $this->assertContains($this->tags[0]->id, $this->tags[1]->related()->pluck('id'));

        $this->tags[1]->related()->detach($this->tags[0]);
        $this->assertEmpty($this->tags[0]->related);
        $this->assertEmpty($this->tags[1]->related);
    }

    public function test_sync()
    {
        $this->tags[0]->related()->sync([ $this->tags[1]->id ]);
        $this->assertContains($this->tags[0]->id, $this->tags[1]->related()->pluck('id'));

        $this->tags[1]->related()->sync([]);
        $this->assertEmpty($this->tags[0]->related);
    }

    public function test_eager_loading()
    {
        $this->tags[0]->related()->sync($this->tags->pluck('id')->toArray());
        $tags = Tag::whereKey($this->tags[0]->id)->with('related')->get();
        $relatedIds = $tags->first()->related->pluck('id');
        $this->tags->each(function($item) use ($relatedIds) {
            $this->assertContains($item->id, $relatedIds);
        });
    }

    public function test_with_count()
    {
        $this->tags[0]->related()->sync($this->tags->pluck('id')->toArray());
        $tag = Tag::whereKey($this->tags[0]->id)->withCount('related')->first();
        $this->assertEquals($this->tags->count(), $tag->related_count);
    }

    public function test_fix_pivot_table()
    {
        $this->tags[0]->related()->sync($this->tags->pluck('id')->toArray());

        $pivot = $this->tags[0]->related()->newPivot();
        $foreignKey = $pivot->getForeignKey();
        $relatedKey = $pivot->getRelatedKey();
        $pivot->$foreignKey = $this->tags[4]->id;
        $pivot->$relatedKey = $this->tags[2]->id;
        $pivot->save();

        $this->artisan('smoothie:fix-pivots', ['model' => Tag::class, 'relationName' => 'related']);

        $connection = $this->tags[0]->getConnection();
        $table = $this->tags[0]->related()->getTable();
        $pivots = $connection->table($table)
                ->orderBy($foreignKey)
                ->orderBy($relatedKey)
                ->get();

        $expected = $this->tags->map(function($item) use ($foreignKey, $relatedKey) {
            return (object) [
                $foreignKey => $this->tags[0]->id,
                $relatedKey => $item->id,
            ];
        });
        $expected->push((object) [
            $foreignKey => $this->tags[2]->id,
            $relatedKey => $this->tags[4]->id,
        ]);

        $this->assertEquals($expected->toArray(), $pivots->toArray());
    }

    public function test_pivot_query()
    {
        $this->tags[1]->related()->sync([ $this->tags[0]->id, $this->tags[2]->id ]);

        $results = $this->tags[1]->related()->newPivotStatementForId($this->tags[0]->id)->get();
        $this->assertEquals(1, $results->count());
        $this->assertEquals($this->tags[0]->id, $results->first()->other_tag_id);

        $results = $this->tags[1]->related()->newPivotStatementForId($this->tags[2]->id)->get();
        $this->assertEquals(1, $results->count());
        $this->assertEquals($this->tags[2]->id, $results->first()->other_tag_id);
    }
}
