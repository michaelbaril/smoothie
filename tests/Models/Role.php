<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Concerns\HasMultiManyRelationships;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasMultiManyRelationships;

    protected $guarded = [];

    public function projects()
    {
        return $this->belongsToMultiMany(Project::class, 'project_role_user');
    }

    public function users()
    {
        return $this->belongsToMultiMany(User::class, 'project_role_user');
    }
}
