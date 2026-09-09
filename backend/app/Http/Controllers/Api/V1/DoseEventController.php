<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoseEventRequest;
use App\Http\Resources\DoseEventResource;
use App\Models\DoseEvent;
use App\Models\Intake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DoseEventController extends Controller
{
    /**
     * Display a listing of the dose events.
     */
    public function index(): AnonymousResourceCollection
    {
        return DoseEventResource::collection(DoseEvent::with('intake')->latest()->get());
    }

    /**
     * Store a newly created dose event in storage.
     *
     * The event always lands on the schedule the dose belongs to: derived
     * from the intake for fixed-times meds, or taken directly from the
     * payload for interval meds (which have no intake grid).
     */
    public function store(StoreDoseEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $scheduleId = $validated['schedule_id']
            ?? Intake::findOrFail($validated['intake_id'])->schedule_id;

        $doseEvent = DoseEvent::create([
            'schedule_id' => $scheduleId,
            'intake_id' => $validated['intake_id'] ?? null,
            'outcome' => $validated['outcome'],
            'taken_at' => $validated['taken_at'] ?? null,
        ]);

        return (new DoseEventResource($doseEvent))->response()
            ->setStatusCode(201);
    }
}
