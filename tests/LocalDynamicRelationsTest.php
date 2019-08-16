<?php

namespace Baril\Smoothie\Tests;

use BadMethodCallException;
use Baril\Smoothie\Tests\Models\Status;

class LocalDynamicRelationsTest extends GlobalDynamicRelationsTest
{
    protected $relationName = 'localRelation';

    protected function setUp() : void
    {
        parent::setUp();
        $this->articles[0]->defineRelation('localRelation', function () {
            return $this->belongsTo(Status::class, 'status_id');
        });
    }

    public function test_other_model_does_not_have_relation()
    {
        $this->expectException(BadMethodCallException::class);
        $this->articles[1]->localRelation();
    }
}
