<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Services\RefillForecast;
use App\Services\RefillForecaster;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Package */
class PackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The refill forecast is derived server-side from the medication's
     * schedule; it is null when no consumption can be derived.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $forecast = app(RefillForecaster::class)->forecastFor($this->resource);

        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'remaining_doses' => $this->remaining_doses,
            'created_at' => $this->created_at,
            'forecast' => $forecast === null ? null : $this->forecastPayload($forecast),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forecastPayload(RefillForecast $forecast): array
    {
        return [
            'status' => $forecast->status,
            'doses_remaining' => $forecast->dosesRemaining,
            'doses_per_day' => $forecast->dosesPerDay,
            'days_of_supply_left' => $forecast->daysOfSupplyLeft,
            'refill_by' => $forecast->refillBy->toDateString(),
            'refill_by_day' => $forecast->refillBy->format('l'),
            'message' => $forecast->message,
        ];
    }
}
