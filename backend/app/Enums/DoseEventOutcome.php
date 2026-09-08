<?php

namespace App\Enums;

/**
 * The outcome of a dose event — what happened to a scheduled intake.
 *
 * @see CONTEXT.md ("shift": the core behavior re-times remaining intakes
 *      after a dose is taken late).
 */
enum DoseEventOutcome: string
{
    /** The dose was taken at its scheduled time. */
    case OnTime = 'on_time';

    /** The dose was taken, but later than its scheduled time (taken_at holds the actual time). */
    case Late = 'late';

    /** The dose was not taken. */
    case Skipped = 'skipped';
}
