<?php

namespace Baril\Smoothie\Concerns;

use Illuminate\Support\Str;

/**
 * @property array $aliases
 * @property string $columnsPrefix
 */
trait AliasesAttributes
{
    protected function getColumnsPrefix()
    {
        return property_exists($this, 'columnsPrefix') ? $this->columnsPrefix : null;
    }

    /**
     * Returns the available aliases for this attribute.
     *
     * @param string $attribute
     * @return array
     */
    protected function getAliases($attribute = null)
    {
        if (!func_num_args()) {
            return property_exists($this, 'aliases') ? $this->aliases : [];
        }

        $aliases = [];
        if (false !== ($alias = array_search($attribute, $this->getAliases() ?? []))) {
            $aliases[] = $alias;
        }
        $columnsPrefix = $this->getColumnsPrefix();
        if ($columnsPrefix && Str::startsWith($attribute, $columnsPrefix)) {
            $aliases[] = Str::replaceFirst($columnsPrefix, '', $attribute);
        }
        return $aliases;
    }

    /**
     * Returns the name of the actual attribute or false.
     *
     * @param string $alias
     * @return string|false
     */
    protected function isAlias($alias)
    {
        if (array_key_exists($alias, $this->aliases ?? [])) {
            return $this->aliases[$alias];
        }
        $columnsPrefix = $this->getColumnsPrefix();
        if ($columnsPrefix && array_key_exists($columnsPrefix . $alias, $this->attributes)) {
            return $columnsPrefix . $alias;
        }
        return false;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return;
        }

        if ($attribute = $this->isAlias($key)) {
            if ($this->hasGetMutator($key)) {
                return $this->mutateAttribute($key, $this->getAttributeFromArray($attribute));
            }
            if ($this->hasCast($key)) {
                return $this->castAttribute($key, $this->getAttributeFromArray($attribute));
            }
            return parent::getAttribute($attribute);
        }

        return parent::getAttribute($key);
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        if ($this->isAlias($key) && !$this->hasGetMutator($key)) {
            return $this->getAttribute($key);
        }
        return parent::mutateAttribute($key, $value);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        if ($attribute = $this->isAlias($key)) {
            // First we will check for the presence of a mutator for the set operation
            // which simply lets the developers tweak the attribute as it is set on
            // the model, such as "json_encoding" an listing of data for storage.
            if ($this->hasSetMutator($key)) {
                return $this->setMutatedAttributeValue($key, $value);
            }

            // If an attribute is listed as a "date", we'll convert it from a DateTime
            // instance into a form proper for storage on the database tables using
            // the connection grammar's date format. We will auto set the values.
            elseif ($value && ($this->isDateAttribute($key) || $this->isDateAttribute($attribute))) {
                $value = $this->fromDateTime($value);
            }

            if ($this->isJsonCastable($key) && ! is_null($value)) {
                $value = $this->castAttributeAsJson($key, $value);
            }

            return parent::setAttribute($attribute, $value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Set a given JSON attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function fillJsonAttribute($key, $value)
    {
        list($key, $path) = explode('->', $key, 2);

        if ($attribute = $this->isAlias($key)) {
            $key = $attribute;
        }

        $this->attributes[$key] = $this->asJson($this->getArrayAttributeWithValue(
            $path, $key, $value
        ));

        return $this;
    }
}
