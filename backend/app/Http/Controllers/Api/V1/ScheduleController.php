<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the schedules.
     */
    public function index(): AnonymousResourceCollection
    {
        return ScheduleResource::collection(
            Schedule::with('intakes')->latest()->get(),
        );
    }

    /**
     * Store a newly created schedule with its intakes in storage.
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $schedule = Schedule::create([
            'medication_id' => $validated['medication_id'],
        ]);

        foreach ($validated['intakes'] as $sortOrder => $intakeData) {
            $schedule->intakes()->create([
                'time' => $intakeData['time'],
                'sort_order' => $intakeData['sort_order'] ?? $sortOrder,
            ]);
        }

        return (new ScheduleResource($schedule->load('intakes')))->response()
            ->setStatusCode(201);
    }
}
