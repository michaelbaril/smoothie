<?php

namespace Baril\Smoothie;

use Illuminate\Support\Carbon as LaravelCarbon;

class Carbon extends LaravelCarbon
{
    protected $hasMonth = true;
    protected $hasDay = true;

    public static function create($year = null, $month = null, $day = null, $hour = null, $minute = null, $second = null, $tz = null)
    {
        $hasMonth = !empty($month);
        $hasDay = !empty($day);
        $month = $hasMonth ? $month : 1;
        $day = $hasDay ? $day : 1;
        $instance = parent::create($year, $month, $day, $hour, $minute, $second, $tz);
        $instance = static::instance($instance);
        $instance->hasMonth = $hasMonth;
        $instance->hasDay = $hasDay;
        return $instance;
    }

    public static function createFromFormat($format, $time, $tz = null)
    {
        $hasMonth = $hasDay = true;
        if ($format === 'Y-m-d' && preg_match('/\-00($|\-)/', $time)) {
            if (substr($time, -6, 3) === '-00') {
                $hasMonth = $hasDay = false;
                $time = substr($time, 0, 4) . '-01' . substr($time, -3);
            }
            if (substr($time, -3) === '-00') {
                $hasDay = false;
                $time = substr($time, 0, 7) . '-01';
            }
        }
        $instance = parent::createFromFormat($format, $time, $tz);
        $instance = static::instance($instance);
        $instance->hasMonth = $hasMonth;
        $instance->hasDay = $hasDay;
        return $instance;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'month':
                return ($this->hasMonth ? parent::__get('month') : null);
            case 'day':
                return ($this->hasDay ? parent::__get('day') : null);
            default:
                return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'month':
                $this->hasMonth = !empty($value);
                if (empty($value)) {
                    $value = 1;
                }
                break;
            case 'day':
                $this->hasDay = !empty($value);
                if (empty($value)) {
                    $value = 1;
                }
                break;
        }
        parent::__set($name, $value);
    }

    public function isFuzzy()
    {
        return !$this->hasMonth || !$this->hasDay;
    }

    public function formatLocalized($format, $formatMonth = null, $formatYear = null)
    {
        if ($this->hasDay) {
            return parent::formatLocalized($format);
        }
        if ($this->hasMonth) {
            return parent::formatLocalized($formatMonth ?? $format);
        }
        return parent::formatLocalized($formatYear ?? $format);
    }

    public function format($format, $formatMonth = null, $formatYear = null)
    {
        if ($this->hasDay) {
            return parent::format($format);
        }
        if ($this->hasMonth) {
            return parent::format($formatMonth ?? $format);
        }
        return parent::format($formatYear ?? $format);
    }
}
