<?php

namespace Baril\Smoothie\Concerns;

trait AliasesAttributesWithCache
{
    use AliasesAttributes, CachesAccessors {
        AliasesAttributes::mutateAttribute as AliasesAttributes_mutateAttribute;
        AliasesAttributes::setAttribute as AliasesAttributes_setAttribute;
        CachesAccessors::getClearList as CachesAccessors_getClearList;
        CachesAccessors::mutateAttribute as CachesAccessors_mutateAttribute;
        CachesAccessors::setAttribute as CachesAccessors_setAttribute;
    }

    protected function getClearList($key)
    {
        $clearList = $this->CachesAccessors_getClearList($key);
        if ($attribute = $this->isAlias($key)) {
            $clearList[] = $attribute;
        }
        return array_merge($clearList, $this->getAliases($key));
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
            return $this->AliasesAttributes_mutateAttribute($key, $value);
        }
        if (!array_key_exists($key, $this->accessed)) {
            $this->accessed[$key] = $this->AliasesAttributes_mutateAttribute($key, $value);
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
        return $this->AliasesAttributes_setAttribute($key, $value);
    }
}
