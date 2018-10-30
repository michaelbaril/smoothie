<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Concerns\HasMultiManyRelationships;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasMultiManyRelationships;

    protected $guarded = [];

    public function projects()
    {
        return $this->belongsToMultiMany(Project::class, 'project_role_user');
    }

    public function roles()
    {
        return $this->belongsToMultiMany(Role::class, 'project_role_user');
    }

    public function birthCountry()
    {
        return $this->belongsTo(Country::class)->usingCache();
    }

    public function citizenships()
    {
        return $this->belongsToMany(Country::class)->usingCache();
    }
}
