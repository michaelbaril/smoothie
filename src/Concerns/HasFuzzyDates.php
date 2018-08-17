<?php

namespace Baril\Smoothie\Concerns;

use DateTimeInterface;
use Baril\Smoothie\Carbon;

trait HasFuzzyDates
{
    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \Illuminate\Support\Carbon
     */
    protected function asDateTime($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon) {
            return $value;
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return new Carbon(
                $value->format('Y-m-d H:i:s.u'),
                $value->getTimezone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        return Carbon::createFromFormat(
            str_replace('.v', '.u', $this->getDateFormat()),
            $value
        );
    }

    protected function mergeDate($yearAttribute, $monthAttribute = null, $dayAttribute = null)
    {
        if (func_num_args() == 1) {
            $dayAttribute = $yearAttribute . '_day';
            $monthAttribute = $yearAttribute . '_month';
            $yearAttribute = $yearAttribute . '_year';
        }

        $year = $this->attributes[$yearAttribute];
        $month = $this->attributes[$monthAttribute];
        $day = $this->attributes[$dayAttribute];

        if (!$year) {
            return null;
        }
        return $this->asDateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    protected function splitDate($value, $yearAttribute, $monthAttribute = null, $dayAttribute = null)
    {
        if (func_num_args() == 2) {
            $dayAttribute = $yearAttribute . '_day';
            $monthAttribute = $yearAttribute . '_month';
            $yearAttribute = $yearAttribute . '_year';
        }
        if (is_null($value)) {
            $this->attributes[$yearAttribute]
                    = $this->attributes[$monthAttribute]
                    = $this->attributes[$dayAttribute]
                    = null;
            return;
        }
        if (!($value instanceof Carbon)) {
            $value = $this->asDateTime($value);
        }
        $this->attributes[$yearAttribute] = $value->year;
        $this->attributes[$monthAttribute] = $value->month;
        $this->attributes[$dayAttribute] = $value->day;
    }
}
