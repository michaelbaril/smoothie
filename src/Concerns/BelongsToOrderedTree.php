<?php

namespace Baril\Smoothie\Concerns;

trait BelongsToOrderedTree
{
    use BelongsToTree {
        children as _children;
        setChildrenFromDescendants as _setChildrenFromDescendants;
    }
    use Orderable;

    /**
     * @return string
     */
    public function getGroupColumn()
    {
        return $this->getParentForeignKeyName();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relation\HasMany
     */
    public function children()
    {
        return static::_children()->ordered();
    }

    protected function setChildrenFromDescendants($descendants)
    {
        $this->_setChildrenFromDescendants($descendants->sortBy($this->getOrderColumn()));
    }
}
