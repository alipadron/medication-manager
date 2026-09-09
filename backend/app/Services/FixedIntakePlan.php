<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * One intake of a fixed-times day, with its authoritative planned time.
 *
 * status: scheduled | skipped | on_time | late | deferred. skipped and
 * late flag what the dose event recorded; on_time/late intakes were taken
 * (plannedAt holds the actual moment for a late one); deferred intakes
 * have no time left in the day (their shift would cross midnight).
 */
class FixedIntakePlan
{
    public function __construct(
        public readonly int $intakeId,
        public readonly string $time,
        public readonly ?CarbonImmutable $plannedAt,
        public readonly string $status,
    ) {}
}
