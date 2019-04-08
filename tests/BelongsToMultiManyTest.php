<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Project;
use Baril\Smoothie\Tests\Models\Role;
use Baril\Smoothie\Tests\Models\User;

class BelongsToMultiManyTest extends TestCase
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
        $user = $this->users[0];
        $projects = $user->projects;
        $this->assertCount(3, $projects);
    }

    public function test_unfolded_querying()
    {
        $user = $this->users[0];
        $projects = $user->projects()->unfolded()->get();
        $this->assertCount(5, $projects);
    }

    public function test_chaining()
    {
        $user = $this->users[0];
        $projects = $user->projects()->orderBy('id')->get();
        $this->assertCount(2, $projects[0]->roles);
    }

    public function test_unconstrained_chaining()
    {
        $user = $this->users[0];
        $projects = $user->projects()->orderBy('id')->get();
        $this->assertCount(3, $projects[0]->roles()->all()->get());
    }

    public function test_for()
    {
        $user = $this->users[0];
        $projects = $user->roles()->for('project', $this->projects[0])->get();
        $this->assertCount(2, $projects);
        $projects = $user->roles()->for('project', $this->projects[0]->id)->get();
        $this->assertCount(2, $projects);
    }

    public function test_eager_loading()
    {
        $users = User::with([
            'projects' => function ($query) { $query->orderBy('id'); },
            'projects.roles' => function ($query) { $query->orderBy('id'); },
        ])->orderBy('id')->get();

        $this->assertTrue($users[0]->relationLoaded('projects'));
        $this->assertCount(3, $users[0]->projects);
        $this->assertTrue($users[0]->projects[0]->relationLoaded('roles'));
        $this->assertCount(2, $users[0]->projects[0]->roles);
    }

    public function test_unconstrained_eager_loading()
    {
        $users = User::with([
            'projects' => function ($query) { $query->orderBy('id'); },
        ])->withAll('projects.roles')->orderBy('id')->get();

        $this->assertTrue($users[0]->relationLoaded('projects'));
        $this->assertCount(3, $users[0]->projects);
        $this->assertTrue($users[0]->projects[0]->relationLoaded('roles'));
        $this->assertCount(3, $users[0]->projects[0]->roles);
    }

    public function test_constrained_attach()
    {
        $this->projects[4]->users()->attach($this->users[4], ['role_id' => $this->roles[4]->id]);
        $this->projects[4]->users()->first()->roles()->attach($this->roles[3]);
        $data = $this->projects[4]->actors->sortBy('role_id')->map(function ($item) {
            return [$item->role_id, $item->user_id];
        })->values()->all();
        $this->assertEquals([
            [$this->roles[3]->id, $this->users[4]->id],
            [$this->roles[4]->id, $this->users[4]->id],
        ], $data);
    }

    public function test_constrained_detach()
    {
        $user = $this->projects[2]->users->first();
        $user->roles()->detach($this->roles[3]);
        $this->assertCount(0, $user->roles);
        $this->assertCount(1, $this->roles[3]->projects);
    }

    public function test_unconstrained_attach()
    {
        $project = $this->users[0]->projects->sortBy('id')->first();
        $project->users()->all()->attach($this->users[4]);
        $this->assertCount(1, $project->actors->where('role_id', null));
    }

    public function test_unconstrained_detach()
    {
        $user = $this->projects[2]->users->first();
        $user->roles()->all()->detach($this->roles[3]);
        $this->assertCount(0, $user->roles);
        $this->assertCount(0, $this->roles[3]->projects);
    }

    public function test_constrained_sync()
    {
        $this->projects[4]->users()->attach($this->users[4], ['role_id' => $this->roles[4]->id]);
        $this->projects[4]->users()->first()->roles()->sync([$this->roles[3]->id, $this->roles[4]->id]);
        $data = $this->projects[4]->actors->sortBy('role_id')->map(function ($item) {
            return [$item->role_id, $item->user_id];
        })->values()->all();
        $this->assertEquals([
            [$this->roles[3]->id, $this->users[4]->id],
            [$this->roles[4]->id, $this->users[4]->id],
        ], $data);
    }
}
