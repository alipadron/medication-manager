<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;

/**
 * A due slot of a schedule at a given moment — the unit of delivery. The
 * slotKey is the canonical identity of the slot (what one push notification
 * covers), against which the no-spam guard persists exactly once.
 */
class DueDose
{
    public function __construct(
        public readonly Schedule $schedule,
        public readonly ?int $intakeId,
        public readonly string $slotKey,
        public readonly CarbonImmutable $dueAt,
    ) {}
}
