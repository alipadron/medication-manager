<?php

namespace App\Http\Resources;

use App\Enums\DosingPattern;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Schedule */
class ScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'dosing_pattern' => $this->dosing_pattern?->value,
            'interval_hours' => $this->interval_hours,
            'nominal_start_at' => $this->nominal_start_at,
            'safe_interval_minutes' => $this->dosing_pattern === DosingPattern::FixedTimes
                ? $this->safe_interval_minutes
                : null,
            'intakes' => IntakeResource::collection($this->whenLoaded('intakes')),
            'created_at' => $this->created_at,
        ];
    }
}
