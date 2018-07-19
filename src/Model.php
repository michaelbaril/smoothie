<?php

namespace Baril\Smoothie;

use Illuminate\Database\Eloquent\Model as LaravelModel;

class Model extends LaravelModel
{
    use Concerns\HasFuzzyDates;
    use Concerns\HasMutualSelfRelationships;
    use Concerns\HasOrderedRelationships;
}
