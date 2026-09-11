<?php

namespace App\Services;

use App\Enums\DosingPattern;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The due slots of a schedule right now, derived exclusively from the
 * authoritative plan engine (#4) — the schedule math is never re-implemented
 * here.
 *
 * Interval meds are due when the planner's rolling due time has arrived.
 * Fixed-times meds are due when a scheduled intake's planned time today has
 * arrived — taken, skipped, and deferred intakes are never offered again.
 */
class DueDoseFinder
{
    public function __construct(private SchedulePlanner $planner) {}

    /**
     * The due slots of a schedule at a given moment.
     *
     * @return array<int, DueDose>
     */
    public function dueDoses(Schedule $schedule, ?CarbonInterface $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return $schedule->dosing_pattern === DosingPattern::Interval
            ? $this->intervalDueDose($schedule, $now)
            : $this->fixedDueDoses($schedule, $now);
    }

    /**
     * Interval: a single rolling due slot, anchored to the last actual dose.
     *
     * @return array<int, DueDose>
     */
    private function intervalDueDose(Schedule $schedule, CarbonInterface $now): array
    {
        $plan = $this->planner->intervalPlan($schedule);

        if ($plan->dueStatus($now) !== 'overdue') {
            return [];
        }

        return [new DueDose(
            schedule: $schedule,
            intakeId: null,
            slotKey: 'interval:'.$schedule->id.':'.$plan->dueAt->toIso8601String(),
            dueAt: $plan->dueAt,
        )];
    }

    /**
     * Fixed-times: each scheduled intake whose planned time has arrived.
     *
     * @return array<int, DueDose>
     */
    private function fixedDueDoses(Schedule $schedule, CarbonInterface $now): array
    {
        $dayPlan = $this->planner->fixedDayPlan($schedule, $now->toDateString());
        $due = [];

        foreach ($dayPlan->intakes as $intake) {
            $isDue = $intake->status === 'scheduled'
                && $intake->plannedAt !== null
                && $intake->plannedAt->lte($now);

            if ($isDue) {
                $due[] = new DueDose(
                    schedule: $schedule,
                    intakeId: $intake->intakeId,
                    slotKey: 'fixed:'.$intake->intakeId.':'.$dayPlan->date,
                    dueAt: $intake->plannedAt,
                );
            }
        }

        return $due;
    }
}
