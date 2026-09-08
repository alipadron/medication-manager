<?php

namespace App\Services;

use App\Enums\DoseEventOutcome;

/**
 * One intake of a shifted schedule: the base scheduled time of day and the
 * outcome the engine derived for the day (still scheduled, or resolved by a
 * dose event, or deferred past midnight).
 */
final readonly class ShiftedIntake
{
    public function __construct(
        public int $intakeId,
        public int $sortOrder,
        public string $time,
        public ?DoseEventOutcome $outcome,
        public ?string $shiftedTime,
        public ?string $takenAt,
        public bool $deferred = false,
    ) {}

    /**
     * The status surfaced to clients: on_time/late/skipped when a dose event
     * resolved this intake, deferred when the shift would cross midnight, and
     * scheduled otherwise.
     */
    public function status(): string
    {
        if ($this->outcome !== null) {
            return $this->outcome->value;
        }

        return $this->deferred ? 'deferred' : 'scheduled';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->intakeId,
            'time' => $this->time,
            'sort_order' => $this->sortOrder,
            'status' => $this->status(),
            'shifted_time' => $this->shiftedTime,
            'taken_at' => $this->takenAt,
        ];
    }
}
