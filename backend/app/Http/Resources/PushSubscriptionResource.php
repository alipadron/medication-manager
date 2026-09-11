<?php

namespace App\Http\Resources;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PushSubscription */
class PushSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The VAPID encryption keys (p256dh/auth) are deliberately excluded: the
     * client already holds them and they grant push delivery to this device.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'endpoint' => $this->endpoint,
            'created_at' => $this->created_at,
        ];
    }
}
