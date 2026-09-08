<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoseEventRequest;
use App\Http\Resources\DoseEventResource;
use App\Models\DoseEvent;
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
     */
    public function store(StoreDoseEventRequest $request): JsonResponse
    {
        $doseEvent = DoseEvent::create($request->validated());

        return (new DoseEventResource($doseEvent))->response()
            ->setStatusCode(201);
    }
}
