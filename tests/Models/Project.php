<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Concerns\HasMultiManyRelationships;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasMultiManyRelationships;

    protected $guarded = [];

    public function roles()
    {
        return $this->belongsToMultiMany(Role::class, 'project_role_user');
    }

    public function users()
    {
        return $this->belongsToMultiMany(User::class, 'project_role_user');
    }

    public function actors()
    {
        return $this->wrapMultiMany([
            'role' => $this->roles(),
            'user' => $this->users(),
        ]);
    }

    public function actorsWithTimestamps()
    {
        return $this->wrapMultiMany([
            'role' => $this->belongsToMultiMany(Role::class, 'project_role_user_wt'),
            'user' => $this->belongsToMultiMany(User::class, 'project_role_user_wt'),
        ])->withTimestamps();
    }
}
