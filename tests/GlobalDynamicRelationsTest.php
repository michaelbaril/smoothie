<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Article;
use Baril\Smoothie\Tests\Models\Status;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalDynamicRelationsTest extends TestCase
{
    protected $articles;
    protected $status;
    protected $relationName = 'globalRelation';

    protected function setUp() : void
    {
        parent::setUp();
        $this->articles = factory(Article::class, 2)->create();
        $this->status = factory(Status::class)->create();
        Article::defineRelation('globalRelation', function () {
            return $this->belongsTo(Status::class, 'status_id');
        });
    }

    public function test_return_relation()
    {
        $relation = $this->articles[0]->{$this->relationName}();
        return $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_update_relation()
    {
        $this->articles[0]->{$this->relationName}()->associate($this->status);
        $this->assertEquals($this->status->id, $this->articles[0]->status_id);
    }

    public function get_query_relation()
    {
        $this->articles[0]->{$this->relationName}()->associate($this->status);
        $status = $this->articles[0]->{$this->relationName}()->get();
        $this->assertCount(1, $status);
        $this->assertEquals($this->status->id, $status[0]->id);
    }

    public function test_dynamic_property()
    {
        $this->articles[0]->{$this->relationName}()->associate($this->status);
        $status = $this->articles[0]->{$this->relationName};
        $this->assertInstanceOf(Status::class, $status);
        $this->assertEquals($this->status->id, $status->id);
    }
}
