<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Model;

class Status extends Model
{
    use \Baril\Smoothie\Concerns\Orderable;

    protected $fillable = ['name'];
}
