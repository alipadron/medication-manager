<?php

namespace App\Services;

use App\Enums\DosingPattern;
use App\Models\Package;
use App\Models\Schedule;
use Carbon\CarbonImmutable;

/**
 * The stock-logic seam — derives a package's refill forecast from its
 * remaining doses and the expected consumption of the medication's schedule.
 *
 * Expected consumption is the schedule's daily dose count: one intake per
 * clock-time slot for fixed-times meds, 24 / interval_hours for interval
 * meds. That daily rate feeds a calendar-day approximation (deliberately not
 * a slot-by-slot plan): the package empties ceil(remaining / dosesPerDay)
 * days from today, and a refill is due when that empty date falls within the
 * warning lead. A package cannot be forecast when the medication has no
 * schedule (or one with no consumable cadence).
 */
class RefillForecaster
{
    /**
     * How many days of notice a refill warning gives by default.
     */
    public const DEFAULT_REFILL_LEAD_DAYS = 3;

    /**
     * Forecast the package's depletion against its medication's schedule.
     *
     * @return RefillForecast|null Null when no consumption can be derived.
     */
    public function forecastFor(Package $package, int $leadDays = self::DEFAULT_REFILL_LEAD_DAYS): ?RefillForecast
    {
        $dosesPerDay = $this->expectedDosesPerDay($package->medication_id);

        if ($dosesPerDay === null) {
            return null;
        }

        $today = CarbonImmutable::today();
        $remaining = $package->remaining_doses;

        if ($remaining === 0) {
            return new RefillForecast(
                dosesRemaining: 0,
                dosesPerDay: $dosesPerDay,
                daysOfSupplyLeft: 0.0,
                refillBy: $today,
                status: 'empty',
                message: 'Out of doses, refill now',
            );
        }

        $daysOfSupplyLeft = round($remaining / $dosesPerDay, 1);
        $refillBy = $today->addDays((int) ceil($remaining / $dosesPerDay) - 1);
        $status = $refillBy->lte($today->addDays($leadDays)) ? 'refill_soon' : 'ok';
        $unit = $remaining === 1 ? 'dose' : 'doses';

        return new RefillForecast(
            dosesRemaining: $remaining,
            dosesPerDay: $dosesPerDay,
            daysOfSupplyLeft: $daysOfSupplyLeft,
            refillBy: $refillBy,
            status: $status,
            message: sprintf('%d %s left, refill by %s', $remaining, $unit, $refillBy->format('l')),
        );
    }

    /**
     * The medication's expected daily dose count from its latest schedule.
     *
     * @return float|null Null when the medication has no schedule, or its
     *                    schedule cannot be consumed (no interval length,
     *                    no fixed-times intakes).
     */
    private function expectedDosesPerDay(int $medicationId): ?float
    {
        $schedule = Schedule::query()
            ->where('medication_id', $medicationId)
            ->latest('id')
            ->first();

        if ($schedule === null) {
            return null;
        }

        if ($schedule->dosing_pattern === DosingPattern::Interval) {
            $hours = $schedule->interval_hours;

            return ($hours === null || $hours <= 0) ? null : 24.0 / $hours;
        }

        $intakeCount = $schedule->intakes()->count();

        return $intakeCount > 0 ? (float) $intakeCount : null;
    }
}
