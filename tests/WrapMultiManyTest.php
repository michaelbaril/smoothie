<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Project;
use Baril\Smoothie\Tests\Models\Role;
use Baril\Smoothie\Tests\Models\User;
use Illuminate\Support\Arr;

class WrapMultiManyTest extends TestCase
{
    protected $projects;
    protected $roles;
    protected $users;

    protected function setUp() : void
    {
        parent::setUp();
        $this->projects = factory(Project::class, 5)->create();
        $this->roles = factory(Role::class, 5)->create();
        $this->users = factory(User::class, 5)->create();
        \DB::table('project_role_user')->insert([
            [
                'project_id' => $this->projects[0]->id,
                'role_id' => $this->roles[0]->id,
                'user_id' => $this->users[0]->id,
            ],
            [
                'project_id' => $this->projects[0]->id,
                'role_id' => $this->roles[1]->id,
                'user_id' => $this->users[0]->id,
            ],
            [
                'project_id' => $this->projects[1]->id,
                'role_id' => $this->roles[2]->id,
                'user_id' => $this->users[0]->id,
            ],
            [
                'project_id' => $this->projects[1]->id,
                'role_id' => $this->roles[3]->id,
                'user_id' => $this->users[0]->id,
            ],
            [
                'project_id' => $this->projects[2]->id,
                'role_id' => $this->roles[3]->id,
                'user_id' => $this->users[0]->id,
            ],
            [
                'project_id' => $this->projects[1]->id,
                'role_id' => $this->roles[1]->id,
                'user_id' => $this->users[1]->id,
            ],
            [
                'project_id' => $this->projects[0]->id,
                'role_id' => $this->roles[2]->id,
                'user_id' => $this->users[1]->id,
            ],
            [
                'project_id' => $this->projects[3]->id,
                'role_id' => null,
                'user_id' => $this->users[1]->id,
            ],
        ]);
    }

    public function test_basic_usage()
    {
        $actors = Project::find($this->projects[0]->id)->actors;
        $this->assertCount(3, $actors);
        $actors = $actors->sortByDesc(function ($item) { return $item->role->id; });
        $this->assertEquals($this->roles[2]->id, $actors->first()->role->id);
        $this->assertEquals($this->users[1]->id, $actors->first()->user->id);
    }

    public function test_eager_loading()
    {
        $projects = Project::with([
            'actors' => function ($query) { $query->orderBy('role_id')->orderBy('user_id'); },
            'actors.role',
            'actors.user',
        ])->orderBy('id')->get();
        $this->assertTrue($projects[0]->relationLoaded('actors'));
        $this->assertTrue($projects[0]->actors[0]->relationLoaded('role'));
        $this->assertTrue($projects[0]->actors[0]->relationLoaded('user'));
        $this->assertEquals($this->roles[1]->id, $projects[0]->actors[1]->role->id);
        $this->assertEquals($this->roles[0]->id, $projects[0]->actors[1]->user->id);
    }

    public function test_null()
    {
        $this->assertNull($this->projects[3]->actors[0]->role);
    }

    /**
     * @dataProvider attachDetachSyncProvider
     */
    public function test_attach_detach_and_sync($i)
    {
        list($data, $count) = $this->getAttachDetachSyncProvidedData($i);
        $project = $this->projects[3];
        $project->actors()->attach($data);
        $this->assertEquals($count + 1, $project->actors()->count());
        $this->assertSync($data, $project->actors()->sync($data));
        $this->assertEquals($count, $project->actors()->count());
        $project->actors()->attach(['role' => 4, 'user' => 4]);
        $this->assertEquals($count, $project->actors()->detach($data));
        $this->assertEquals(1, $project->actors()->count());
        $this->assertSync($data, $project->actors()->sync($data));
        $this->assertEquals($count, $project->actors()->count());
    }

    protected function assertSync($expected, $actual)
    {
        $expected = collect($this->projects[0]->actors()->parseIds($expected))
                ->sortBy('role_id')->sortBy('user_id')->values();
        foreach (['attached', 'detached', 'updated'] as $key) {
            $this->assertArrayHasKey($key, $actual);
            $this->assertTrue(is_array($actual[$key]));
            $this->assertFalse(Arr::isAssoc($actual[$key]));
        }
        $synced = collect(array_merge($actual['updated'], $actual['attached']))
                ->sortBy('role_id')->sortBy('user_id')->values();
        return $this->assertArraySubset($synced, $expected);
    }

    public function test_attach_with_attributes()
    {
        $project = $this->projects[4];
        $project->actors()->attach(['role' => $this->roles[2], 'user' => $this->users[0]], ['test' => 'testouille']);
        $actor = $project->actors->first();
        $this->assertEquals($this->roles[2]->id, $actor->role_id);
        $this->assertEquals('testouille', $actor->test);
    }

    public function test_with_timestamps()
    {
        $project = $this->projects[4];

        $ts = time();
        $project->actorsWithTimestamps()->attach(['role' => $this->roles[2], 'user' => $this->users[0]]);
        $actor = $project->actorsWithTimestamps->first();
        $this->assertNotNull($actor->created_at);
        $this->assertGreaterThanOrEqual($ts, $actor->created_at->timestamp);

        $ts = time();
        $project->actorsWithTimestamps()->sync([['role' => $this->roles[2], 'user' => $this->users[0]]]);
        $actor = $project->actorsWithTimestamps->first();
        $this->assertNotNull($actor->updated_at);
        $this->assertGreaterThanOrEqual($ts, $actor->updated_at->timestamp);
    }

    public function attachDetachSyncProvider()
    {
        $sets = [
            'pivot',
            'pivot collection',
            'array with column names',
            'array with relation names and ids',
            'array with relation names and models',
            'array of arrays',
            'collection',
        ];

        return collect($sets)->mapWithKeys(function ($item, $key) {
            return [$item => [$key]];
        });
    }

    public function getAttachDetachSyncProvidedData($key)
    {
        $data = [
            [$this->projects[0]->actors->first(), 1],
            [$this->projects[0]->actors, 3],
            [['role_id' => $this->roles[0]->id, 'user_id' => $this->users[0]->id], 1],
            [['role' => null, 'user' => $this->users[0]->id], 1],
            [['role' => $this->roles[1], 'user' => $this->users[1]], 1],
            [[
                ['role' => $this->roles[0]->id, 'user' => $this->users[0]->id],
                ['role' => $this->roles[1], 'user' => $this->users[1]],
            ], 2],
            [collect([
                ['role' => $this->roles[0]->id, 'user' => $this->users[0]->id],
                ['role' => $this->roles[1], 'user' => $this->users[1]],
            ]), 2],
        ];
        return $data[$key];
    }

}
