<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DosingPattern;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetSchedulePlanRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Resources\SchedulePlanResource;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Services\SchedulePlanner;
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
     * Store a newly created schedule in storage.
     *
     * Interval schedules carry interval_hours (no intake grid); fixed-times
     * schedules are stored with their clock-time intakes.
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $attributes = [
            'medication_id' => $validated['medication_id'],
            'dosing_pattern' => $validated['dosing_pattern'],
        ];

        if ($validated['dosing_pattern'] === DosingPattern::Interval->value) {
            $attributes['interval_hours'] = $validated['interval_hours'];

            if (isset($validated['nominal_start_at'])) {
                $attributes['nominal_start_at'] = $validated['nominal_start_at'];
            }
        } elseif (isset($validated['safe_interval_minutes'])) {
            $attributes['safe_interval_minutes'] = $validated['safe_interval_minutes'];
        } else {
            $attributes['safe_interval_minutes'] = 240;
        }

        $schedule = Schedule::create($attributes);

        if ($schedule->dosing_pattern === DosingPattern::FixedTimes) {
            foreach ($validated['intakes'] as $sortOrder => $intakeData) {
                $schedule->intakes()->create([
                    'time' => $intakeData['time'],
                    'sort_order' => $intakeData['sort_order'] ?? $sortOrder,
                ]);
            }
        }

        return (new ScheduleResource($schedule->load('intakes')))->response()
            ->setStatusCode(201);
    }

    /**
     * Serve the authoritative plan for a schedule: the rolling due time for
     * interval meds, or that day's (possibly shifted) grid for fixed-times
     * meds.
     */
    public function plan(
        Schedule $schedule,
        GetSchedulePlanRequest $request,
        SchedulePlanner $planner,
    ): SchedulePlanResource {
        $date = $request->validated('date') ?? now()->toDateString();

        $plan = $schedule->dosing_pattern === DosingPattern::Interval
            ? $planner->intervalPlan($schedule)
            : $planner->fixedDayPlan($schedule, $date);

        return new SchedulePlanResource($schedule, $plan);
    }
}
