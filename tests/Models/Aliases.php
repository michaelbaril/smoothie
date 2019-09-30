<?php

namespace Baril\Smoothie\Tests\Models;

use Baril\Smoothie\Concerns\AliasesAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Columns are: pr_id, pr_name, pr_desc, pr_slug, name, description, pr_label, label
 * pr_date, pr_bool, pr_json
 */
class Aliases extends Model
{
    use AliasesAttributes;

    protected $primaryKey = 'pr_id';
    protected $guarded = [];
    protected $casts = [
        'bool' => 'boolean',
        'pr_date' => 'date',
        'pr_json' => 'array',
    ];
    protected $appends = [
        'description',
        'aliased_description',
        'label',
        'publication_date',
    ];

    protected $aliases = [
        'description' => 'pr_desc',
        'aliased_description' => 'description',
        'label' => 'label',
        'publication_date' => 'pr_date',
    ];
    protected $columnsPrefix = 'pr_';

    public function getOriginalAttribute($name)
    {
        return $this->attributes[$name];
    }

    public function getPrIdAttribute($value)
    {
        return 'id' . $value;
    }

    public function setIdAttribute($value)
    {
        $this->attributes['pr_id'] = $value - 1;
    }

    public function getSlugAttribute($value)
    {
        return str_replace('_', '-', $value);
    }

    public function setPrSlugAttribute($value)
    {
        $this->attributes['pr_slug'] = str_replace('-', '_', $value);
    }

    public function forceAttributes($attributes)
    {
        $this->attributes = $attributes;
    }
}
