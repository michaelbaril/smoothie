<?php

namespace Baril\Smoothie\Concerns;

use Illuminate\Support\Str;

/**
 * @property array $cacheable
 * @property array $uncacheable
 * @property array $clearAccessorCache
 */
trait CachesAccessors
{
    protected $accessed = [];

    /**
     * @return array
     */
    protected function getCacheable()
    {
        return property_exists($this, 'cacheable') ? $this->cacheable : ['*'];
    }

    /**
     * @return array
     */
    protected function getUncacheable()
    {
        return property_exists($this, 'uncacheable') ? $this->uncacheable : [];
    }

    /**
     * Checks if the provided $attribute is cacheable.
     *
     * @param string $attribute
     * @return boolean
     */
    protected function isCacheable($attribute)
    {
        if (in_array($attribute, $this->getUncacheable())) {
            return false;
        }
        $cacheable = $this->getCacheable();
        if ($cacheable == ['*'] || in_array($attribute, $cacheable)) {
            return true;
        }
        return false;
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
        if (!$this->isCacheable($key)) {
            return parent::mutateAttribute($key, $value);
        }
        if (!array_key_exists($key, $this->accessed)) {
            $this->accessed[$key] = $this->{'get'.Str::studly($key).'Attribute'}($value);
        }
        return $this->accessed[$key];
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
        $this->clearAccessorCache($key);
        return parent::setAttribute($key, $value);
    }

    protected function getClearList($key)
    {
        $clearList = [];
        if (property_exists($this, 'clearAccessorCache')) {
            $clearList = $this->clearAccessorCache[$key] ?? [];
        }
        $clearList[] = $key;
        return $clearList;
    }

    protected function clearAccessorCache($key)
    {
        $keys = $this->getClearList($key);
        foreach ($keys as $key) {
            unset($this->accessed[$key]);
        }
    }
}
