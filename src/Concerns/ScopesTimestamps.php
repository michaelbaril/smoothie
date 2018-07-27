<?php

namespace Baril\Smoothie\Concerns;

trait ScopesTimestamps
{
    public function scopeOrderByCreation($builder, $direction = 'asc')
    {
        $builder->orderBy(static::CREATED_AT, $direction);
    }
    
    public function scopeOrderByUpdate($builder, $direction = 'asc')
    {
        $builder->orderBy(static::UPDATED_AT, $direction);
    }
    
    public function scopeCreatedAfter($builder, $date, $strict = false)
    {
        $date = $this->asDateTime($date)->format($this->getDateFormat());
        $builder->where(static::CREATED_AT, $strict ? '>' : '>=', $date);
    }
    
    public function scopeCreatedBefore($builder, $date, $strict = false)
    {
        $date = $this->asDateTime($date)->format($this->getDateFormat());
        $builder->where(static::CREATED_AT, $strict ? '<' : '<=', $date);
    }
    
    public function scopeCreatedBetween($builder, $start, $end, $strictStart = false, $strictEnd = false)
    {
        $this->scopeCreatedAfter($builder, $start, $strictStart);
        $this->scopeCreatedBefore($builder, $end, $strictEnd);
    }
    
    public function scopeUpdatedAfter($builder, $date, $strict = false)
    {
        $date = $this->asDateTime($date)->format($this->getDateFormat());
        $builder->where(static::UPDATED_AT, $strict ? '>' : '>=', $date);
    }
    
    public function scopeUpdatedBefore($builder, $date, $strict = false)
    {
        $date = $this->asDateTime($date)->format($this->getDateFormat());
        $builder->where(static::UPDATED_AT, $strict ? '<' : '<=', $date);
    }
    
    public function scopeUpdatedBetween($builder, $start, $end, $strictStart = false, $strictEnd = false)
    {
        $this->scopeUpdatedAfter($builder, $start, $strictStart);
        $this->scopeUpdatedBefore($builder, $end, $strictEnd);
    }
}
