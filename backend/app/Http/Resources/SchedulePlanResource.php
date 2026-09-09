<?php

namespace App\Http\Resources;

use App\Models\Schedule;
use App\Services\FixedDayPlan;
use App\Services\FixedIntakePlan;
use App\Services\IntervalPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authoritative plan read for a schedule: a due time for interval meds,
 * a (possibly shifted) day grid for fixed-times meds.
 */
class SchedulePlanResource extends JsonResource
{
    public function __construct(
        Schedule $schedule,
        public readonly IntervalPlan|FixedDayPlan $plan,
    ) {
        parent::__construct($schedule);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->plan instanceof IntervalPlan
            ? $this->intervalPayload()
            : $this->fixedDayPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function intervalPayload(): array
    {
        /** @var IntervalPlan $plan */
        $plan = $this->plan;

        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'dosing_pattern' => $this->dosing_pattern?->value,
            'interval_hours' => $this->interval_hours,
            'nominal_start_at' => $this->nominal_start_at,
            'last_actual_dose_at' => $plan->lastActualDoseAt,
            'due_at' => $plan->dueAt,
            'due_status' => $plan->dueStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixedDayPayload(): array
    {
        /** @var FixedDayPlan $plan */
        $plan = $this->plan;

        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'dosing_pattern' => $this->dosing_pattern?->value,
            'safe_interval_minutes' => $this->safe_interval_minutes,
            'date' => $plan->date,
            'intakes' => array_map(
                fn (FixedIntakePlan $intake): array => [
                    'id' => $intake->intakeId,
                    'time' => $intake->time,
                    'planned_time' => $intake->plannedAt?->format('H:i'),
                    'status' => $intake->status,
                ],
                $plan->intakes,
            ),
        ];
    }
}
