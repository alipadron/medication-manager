<?php

namespace App\Http\Resources;

use App\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Medication */
class MedicationResource extends JsonResource
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
            'name' => $this->name,
            'person_id' => $this->person_id,
            'safe_interval_minutes' => $this->safe_interval_minutes,
            'created_at' => $this->created_at,
        ];
    }
}
