<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The authoritative plan read for an interval schedule: a single rolling
 * due time anchored to the last actual dose.
 */
class IntervalPlan
{
    public function __construct(
        public readonly ?CarbonImmutable $lastActualDoseAt,
        public readonly ?CarbonImmutable $dueAt,
    ) {}

    /**
     * Whether the dose is overdue (due moment has arrived), upcoming, or
     * unknown because the cadence has no anchor at all.
     */
    public function dueStatus(?CarbonInterface $now = null): string
    {
        if ($this->dueAt === null) {
            return 'unknown';
        }

        return $this->dueAt->lte($now ?? CarbonImmutable::now()) ? 'overdue' : 'upcoming';
    }
}
