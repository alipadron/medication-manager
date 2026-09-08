<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The recomputed schedule for a single day after the shift engine ran:
 * the anchor date, the medication's safe interval, and the shifted intakes
 * in schedule order.
 */
final readonly class ShiftedSchedule
{
    /**
     * @param  Collection<int, ShiftedIntake>  $intakes
     */
    public function __construct(
        public CarbonImmutable $date,
        public int $safeIntervalMinutes,
        public Collection $intakes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'safe_interval_minutes' => $this->safeIntervalMinutes,
            'intakes' => $this->intakes
                ->map(fn (ShiftedIntake $intake): array => $intake->toArray())
                ->values()
                ->all(),
        ];
    }
}
