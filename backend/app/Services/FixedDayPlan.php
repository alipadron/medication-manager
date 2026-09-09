<?php

namespace App\Services;

/**
 * The authoritative plan read for a fixed-times schedule on one date.
 *
 * @property-read list<FixedIntakePlan> $intakes
 */
class FixedDayPlan
{
    /**
     * @param  list<FixedIntakePlan>  $intakes
     */
    public function __construct(
        public readonly string $date,
        public readonly array $intakes,
    ) {}
}
