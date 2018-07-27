<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Tag;

class TimestampScopesTest extends TestCase
{
    protected $models;

    protected function assertResults($expected, $actual, $checkOrder = true)
    {
        $expected = collect($expected)->map(function($i) { return $this->models[$i]->id; })->all();
        $actual = $actual->pluck('id')->all();
        if (!$checkOrder) {
            sort($expected);
            sort($actual);
        }
        $this->assertEquals($expected, $actual);
    }

    public function test_scopes()
    {
        $this->models = [];
        for ($i = 0; $i < 5; $i++) {
            $this->models[] = factory(Tag::class)->create();
            sleep(1);
        }
        $now = now();
        Tag::find($this->models[2]->id)->update(['name' => 'toto']);

        $this->assertResults([0, 1, 2, 3, 4], Tag::orderByCreation()->get());
        $this->assertResults([4, 3, 2, 1, 0], Tag::orderByCreation('desc')->get());
        $this->assertResults([0, 1, 3, 4, 2], Tag::orderByUpdate()->get());
        $this->assertResults([2, 4, 3, 1, 0], Tag::orderByUpdate('desc')->get());
        $this->assertResults([2], Tag::updatedAfter($now)->get());
        $this->assertResults([0, 1, 3, 4], Tag::updatedBefore($now, true)->get(), false);
    }
}
