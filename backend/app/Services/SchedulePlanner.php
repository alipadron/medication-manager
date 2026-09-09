<?php

namespace App\Services;

use App\Enums\DoseEventOutcome;
use App\Models\DoseEvent;
use App\Models\Schedule;
use Carbon\CarbonImmutable;

/**
 * The pattern-aware scheduling engine — the backend's logic seam.
 *
 * Server-authoritative reads: the INTERVAL path answers "when is the next
 * dose due?", the FIXED-TIMES path answers "what is today's (shifted)
 * schedule?". Both are pure functions of a schedule and its dose events;
 * see CONTEXT.md for the domain semantics this encodes.
 */
class SchedulePlanner
{
    /**
     * Interval pattern: the continuous rolling cadence.
     *
     * Next due = last actual dose + interval, where an actual dose is any
     * on-time/late dose event carrying a taken_at. A taken-late dose
     * re-anchors the cadence to its actual time. A skipped dose deletes
     * its cadence slot: a skip recorded at-or-after the current due time
     * rolls the due forward one interval at a time until it is the first
     * slot strictly after the skip's record moment — the slot the skip
     * refers to is gone and earlier elapsed slots are not re-offered, so
     * nothing compresses, nothing is caught up, and a skip never leaves
     * the plan pointing at a due time in the past. The cadence still
     * derives from the last actual dose. With no actual dose yet, the
     * cadence is seeded from the nominal start alignment and rolled
     * forward to the next upcoming slot.
     */
    public function intervalPlan(Schedule $schedule): IntervalPlan
    {
        $hours = $schedule->interval_hours;

        if ($hours === null) {
            return new IntervalPlan(lastActualDoseAt: null, dueAt: null);
        }

        $lastActualDoseAt = null;
        $skips = [];

        foreach ($schedule->doseEvents()->orderBy('created_at')->get() as $event) {
            if ($event->outcome === DoseEventOutcome::Skipped) {
                $skips[] = CarbonImmutable::instance($event->created_at);

                continue;
            }

            if ($event->taken_at === null) {
                continue;
            }

            $takenAt = CarbonImmutable::instance($event->taken_at);

            if ($lastActualDoseAt === null || $takenAt->greaterThan($lastActualDoseAt)) {
                $lastActualDoseAt = $takenAt;
            }
        }

        if ($lastActualDoseAt !== null) {
            $dueAt = $lastActualDoseAt->addHours($hours);

            foreach ($skips as $skipAt) {
                while ($skipAt->gte($dueAt)) {
                    $dueAt = $dueAt->addHours($hours);
                }
            }

            return new IntervalPlan(lastActualDoseAt: $lastActualDoseAt, dueAt: $dueAt);
        }

        if ($schedule->nominal_start_at === null) {
            return new IntervalPlan(lastActualDoseAt: null, dueAt: null);
        }

        $dueAt = CarbonImmutable::instance($schedule->nominal_start_at);
        $now = CarbonImmutable::now();

        while ($dueAt->lte($now)) {
            $dueAt = $dueAt->addHours($hours);
        }

        return new IntervalPlan(lastActualDoseAt: null, dueAt: $dueAt);
    }

    /**
     * Fixed-times pattern: that day's shifted schedule.
     *
     * Intakes run at their clock times and the day resets nightly. A late
     * dose re-times that day's remaining intakes by the delay, but never
     * closer than safe_interval_minutes after the last dose moment (actual
     * or planned). A skipped intake is flagged and moves nothing — an
     * earlier shift still stands, a skip adds no delay. An intake whose
     * shift would cross midnight is deferred (it has no time left today).
     * A dose event belongs to the day of its taken_at, or its created_at
     * when no actual time was recorded.
     *
     * @param  string  $date  The calendar day to plan, formatted Y-m-d.
     */
    public function fixedDayPlan(Schedule $schedule, string $date): FixedDayPlan
    {
        $safeIntervalMinutes = $schedule->safe_interval_minutes ?? 240;
        $dayEnd = CarbonImmutable::parse($date)->addDay();

        $eventsByIntake = $this->eventsByIntakeOn($schedule, $date);

        $pendingDelayMinutes = 0;
        $guard = null;
        $intakes = [];

        foreach ($schedule->intakes()->get() as $intake) {
            $baseAt = CarbonImmutable::parse($date.' '.$intake->time);
            $naiveAt = $baseAt->addMinutes($pendingDelayMinutes);
            $plannedAt = $naiveAt;
            $status = 'scheduled';

            $event = $eventsByIntake[$intake->id] ?? null;

            if ($event !== null) {
                if ($event->outcome === DoseEventOutcome::Skipped) {
                    $status = 'skipped';
                } elseif ($event->outcome === DoseEventOutcome::Late) {
                    $actualAt = CarbonImmutable::instance($event->taken_at);
                    $plannedAt = $actualAt;
                    $status = 'late';
                    $pendingDelayMinutes = (int) round(
                        ($actualAt->getTimestamp() - $naiveAt->getTimestamp()) / 60,
                    );
                } else {
                    $status = 'on_time';
                }
            }

            if ($status === 'scheduled' && $pendingDelayMinutes > 0 && $guard !== null) {
                $boundAt = $guard->addMinutes($safeIntervalMinutes);

                if ($plannedAt->lt($boundAt)) {
                    $plannedAt = $boundAt;
                }
            }

            if ($status === 'scheduled' && $plannedAt->gte($dayEnd)) {
                $status = 'deferred';
                $plannedAt = null;
            }

            if ($plannedAt !== null && in_array($status, ['on_time', 'late', 'scheduled'], true)) {
                $guard = $guard === null || $plannedAt->greaterThan($guard) ? $plannedAt : $guard;
            }

            $intakes[] = new FixedIntakePlan(
                intakeId: $intake->id,
                time: $intake->time,
                plannedAt: $plannedAt,
                status: $status,
            );
        }

        return new FixedDayPlan(date: $date, intakes: $intakes);
    }

    /**
     * The dose events belonging to the given calendar day, keyed by intake.
     *
     * When several events hit the same intake that day, the latest wins.
     *
     * @return array<int, DoseEvent>
     */
    private function eventsByIntakeOn(Schedule $schedule, string $date): array
    {
        $eventsByIntake = [];

        foreach ($schedule->doseEvents()->get() as $event) {
            if ($event->intake_id === null) {
                continue;
            }

            $eventMoment = $event->taken_at ?? $event->created_at;

            if ($eventMoment->toDateString() !== $date) {
                continue;
            }

            $current = $eventsByIntake[$event->intake_id] ?? null;
            $currentMoment = $current?->taken_at ?? $current?->created_at;

            if ($current === null || $eventMoment->greaterThan($currentMoment)) {
                $eventsByIntake[$event->intake_id] = $event;
            }
        }

        return $eventsByIntake;
    }
}
