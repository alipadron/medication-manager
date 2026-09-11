<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Http\Resources\PushSubscriptionResource;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    /**
     * Store a newly enrolled device subscription in storage.
     */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $subscription = PushSubscription::create($request->validated());

        return (new PushSubscriptionResource($subscription))->response()
            ->setStatusCode(201);
    }

    /**
     * Remove a device subscription from storage (e.g. when a delivery fails
     * or the user unsubscribes from notifications).
     */
    public function destroy(PushSubscription $pushSubscription): JsonResponse
    {
        $pushSubscription->delete();

        return response()->json(null, 204);
    }
}
