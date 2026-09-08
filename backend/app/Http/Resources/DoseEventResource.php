<?php

namespace App\Http\Resources;

use App\Models\DoseEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DoseEvent */
class DoseEventResource extends JsonResource
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
            'intake_id' => $this->intake_id,
            'outcome' => $this->outcome?->value,
            'taken_at' => $this->taken_at,
            'created_at' => $this->created_at,
        ];
    }
}
