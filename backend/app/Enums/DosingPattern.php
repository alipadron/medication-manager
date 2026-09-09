<?php

namespace App\Enums;

/**
 * How a medication's doses are timed — exactly one per schedule.
 *
 * @see CONTEXT.md ("dosing pattern": interval vs fixed-times).
 */
enum DosingPattern: string
{
    /** "Every N hours": a continuous rolling cadence re-anchored to the last actual dose. */
    case Interval = 'interval';

    /** Specific clock times each day; the day resets nightly. */
    case FixedTimes = 'fixed_times';
}
