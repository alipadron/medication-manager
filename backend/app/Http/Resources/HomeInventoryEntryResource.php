<?php

namespace App\Http\Resources;

use App\Models\HomeInventoryEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HomeInventoryEntry */
class HomeInventoryEntryResource extends JsonResource
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
            'expiry_date' => $this->expiry_date?->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
