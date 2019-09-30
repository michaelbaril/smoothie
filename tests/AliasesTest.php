<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Aliases;
use Illuminate\Support\Carbon;

class AliasesTest extends TestCase
{
    protected $model;

    protected function setUp() : void
    {
        parent::setUp();
        $this->model = new Aliases();
        $this->model->forceAttributes([
            'pr_id' => 1,
            'pr_name' => 'pr_name',
            'pr_desc' => 'pr_desc',
            'pr_slug' => 'pr_slug',
            'name' => 'name',
            'description' => 'description',
            'pr_label' => 'pr_label',
            'label' => 'label',
            'pr_date' => '2018-09-10',
            'pr_bool' => 1,
            'pr_json' => '{"attribute": "value"}',
        ]);
    }

    public function test_real_attributes()
    {
        $this->model->pr_name = 'toto';
        $this->assertEquals('toto', $this->model->pr_name);
    }

    public function test_alias_overrides_real_attribute()
    {
        $this->assertEquals('pr_desc', $this->model->description);
        $this->model->description = 'toto';
        $this->assertEquals('toto', $this->model->pr_desc);
    }

    public function test_prefix_overrides_real_attribute()
    {
        $this->assertEquals('pr_name', $this->model->name);
        $this->model->name = 'toto';
        $this->assertEquals('toto', $this->model->pr_name);
    }

    public function test_alias_to_overridden_attribute()
    {
        $this->assertEquals('description', $this->model->aliased_description);
    }

    public function test_accessor_on_real_attribute()
    {
        $this->assertEquals('id1', $this->model->pr_id);
        $this->assertEquals('id1', $this->model->id);
    }

    public function test_accessor_on_alias()
    {
        $this->assertEquals('pr-slug', $this->model->slug);
        $this->assertEquals('pr_slug', $this->model->pr_slug);
    }

    public function test_mutator_on_real_attribute()
    {
        $this->model->slug = 'pr-slug';
        $this->assertEquals('pr_slug', $this->model->pr_slug);
        $this->model->pr_slug = 'pr-slug';
        $this->assertEquals('pr_slug', $this->model->pr_slug);
    }

    public function test_mutator_on_alias()
    {
        $this->model->pr_id = 1;
        $this->assertEquals('id1', $this->model->pr_id);
        $this->model->id = 1;
        $this->assertEquals('id0', $this->model->pr_id);
    }

    public function test_priorities()
    {
        $this->assertEquals('label', $this->model->label);
        $this->model->label = 'toto';
        $this->assertEquals('pr_label', $this->model->pr_label);
    }

    public function test_casts()
    {
        $this->assertInstanceOf(Carbon::class, $this->model->pr_date);
        $this->assertInstanceOf(Carbon::class, $this->model->publication_date);
        $this->assertEquals('2018-09-10', $this->model->publication_date->format('Y-m-d'));
        $this->model->publication_date = Carbon::createFromFormat('Y-m-d', '2017-01-01')->startOfDay();
        $this->assertEquals('2017-01-01 00:00:00', $this->model->getOriginalAttribute('pr_date'));

        $this->assertSame(true, $this->model->bool);
        $this->assertSame(1, $this->model->pr_bool);
    }

    public function test_json()
    {
        $this->model['json->otherAttribute'] = 'otherValue';
        $this->assertEquals(['attribute' => 'value', 'otherAttribute' => 'otherValue'], $this->model->json);
    }

    public function test_to_array()
    {
        $array = $this->model->toArray();
        foreach ([
            'description',
            'aliased_description',
            'label',
            'publication_date',
        ] as $key) {
            $this->assertArrayHasKey($key, $array);
            $this->assertEquals($this->model->$key, $array[$key]);
        }
    }
}
