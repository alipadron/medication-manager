<?php

namespace App\Services;

use App\Enums\DoseEventOutcome;
use App\Models\DoseEvent;
use App\Models\Intake;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The shift engine: the single place that re-times a day's remaining intakes
 * after a dose is taken late, skipped, or on time.
 *
 * Shift rules (v1, documented here because this is the project's logic seam):
 *
 * 1. On time  -> the dose happened at its scheduled time (which may itself
 *                already include earlier shifts); no further delay is added.
 * 2. Late     -> the delay of a late dose is measured against the intake's
 *                CURRENT planned time (so cascades accumulate). Every later
 *                intake shifts by that delay, but never lands closer than
 *                the medication's safe interval after the last actual dose
 *                (or after the preceding shifted intake).
 * 3. Skipped  -> the intake is flagged; nothing else in the day moves
 *                (the shift caused by an EARLIER late dose still stands).
 * 4. Boundary -> the shifted schedule lives inside the calendar day. A shift
 *                that would land at/after 24:00 is clamped to 23:59 when the
 *                safe interval still leaves room in the day; when even the
 *                earliest safe time (last actual dose + interval) is past
 *                midnight, the intake is deferred (flagged, not re-timed).
 */
final class ShiftEngine
{
    private const END_OF_DAY_MINUTES = 23 * 60 + 59;

    /**
     * Recompute the shifted schedule of a schedule for a given calendar day.
     */
    public function shiftForDate(Schedule $schedule, CarbonImmutable $date): ShiftedSchedule
    {
        $intakes = $schedule->intakes()->get()->values();

        $safeIntervalMinutes = $schedule->medication->safe_interval_minutes;

        $events = DoseEvent::query()
            ->whereIn('intake_id', $intakes->pluck('id'))
            ->get()
            ->filter(fn (DoseEvent $event): bool => $event->forDate()->isSameDay($date))
            ->sortByDesc('id')
            ->unique('intake_id')
            ->keyBy('intake_id');

        $planned = $intakes
            ->map(fn (Intake $intake): int => $this->parseTimeOfDay($intake->time))
            ->all();

        $deferred = [];

        foreach ($intakes as $index => $intake) {
            $event = $events->get($intake->id);

            if ($event === null) {
                continue;
            }

            if ($event->outcome === DoseEventOutcome::Late) {
                $this->applyDelay($planned, $index, $this->toMinutesOfDay($event->taken_at), $safeIntervalMinutes, $deferred);
            }
        }

        return new ShiftedSchedule(
            $date,
            $safeIntervalMinutes,
            $this->buildIntakes($intakes, $events, $planned, $deferred),
        );
    }

    /**
     * Shift every intake after a late dose by the delay, enforcing the safe
     * interval and the same-day boundary.
     *
     * @param  array<int, int>  $planned  planned minutes of day, mutated in place
     * @param  array<int, true>  $deferred  intakes that cannot fit today, mutated in place
     */
    private function applyDelay(array &$planned, int $lateIndex, int $actualMinutes, int $safeIntervalMinutes, array &$deferred): void
    {
        $delay = max(0, $actualMinutes - $planned[$lateIndex]);

        $previousMoment = $actualMinutes;

        $count = count($planned);

        for ($index = $lateIndex + 1; $index < $count; $index++) {
            $naive = $planned[$index] + $delay;
            $earliestSafe = $previousMoment + $safeIntervalMinutes;
            $candidate = max($naive, $earliestSafe);

            if ($candidate >= 24 * 60) {
                if ($earliestSafe < 24 * 60) {
                    $candidate = self::END_OF_DAY_MINUTES;
                } else {
                    // Even the earliest safe time is past midnight: the rest of
                    // the day cannot accommodate this intake (nor any later one).
                    for ($rest = $index; $rest < $count; $rest++) {
                        $deferred[$rest] = true;
                    }

                    break;
                }
            }

            $planned[$index] = $candidate;
            $previousMoment = $candidate;
        }
    }

    /**
     * Build the shifted intakes in schedule order.
     *
     * @param  Collection<int, Intake>  $intakes
     * @param  Collection<int, DoseEvent>  $events
     * @param  array<int, int>  $planned
     * @param  array<int, true>  $deferred
     * @return Collection<int, ShiftedIntake>
     */
    private function buildIntakes(Collection $intakes, Collection $events, array $planned, array $deferred): Collection
    {
        return $intakes->map(function (Intake $intake, int $index) use ($events, $planned, $deferred): ShiftedIntake {
            $baseMinutes = $this->parseTimeOfDay($intake->time);
            $event = $events->get($intake->id);

            $shiftedTime = null;
            $deferredIntake = isset($deferred[$index]);

            if ($event === null && ! $deferredIntake && $planned[$index] !== $baseMinutes) {
                $shiftedTime = $this->toTimeOfDay($planned[$index]);
            }

            return new ShiftedIntake(
                intakeId: $intake->id,
                sortOrder: $intake->sort_order,
                time: $intake->time,
                outcome: $event?->outcome,
                shiftedTime: $shiftedTime,
                takenAt: $event?->taken_at?->format('Y-m-d H:i:s'),
                deferred: $deferredIntake,
            );
        });
    }

    private function toMinutesOfDay(?CarbonInterface $time): int
    {
        if ($time === null) {
            return 0;
        }

        return $time->hour * 60 + $time->minute;
    }

    private function parseTimeOfDay(string $timeOfDay): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $timeOfDay));

        return $hours * 60 + $minutes;
    }

    private function toTimeOfDay(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
