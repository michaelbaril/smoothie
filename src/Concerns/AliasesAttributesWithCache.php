<?php

namespace Baril\Smoothie\Concerns;

trait AliasesAttributesWithCache
{
    use AliasesAttributes, CachesAccessors {
        AliasesAttributes::setAttribute as AliasesAttributes_setAttribute;
        CachesAccessors::getClearList as CachesAccessors_getClearList;
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
