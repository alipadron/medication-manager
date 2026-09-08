<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicationRequest;
use App\Http\Resources\MedicationResource;
use App\Models\Medication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicationController extends Controller
{
    /**
     * Display a listing of the medications, optionally filtered by person.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = Medication::with('person')->latest();

        if ($personId = request()->query('person_id')) {
            $query->where('person_id', $personId);
        }

        return MedicationResource::collection($query->get());
    }

    /**
     * Store a newly created medication in storage.
     */
    public function store(StoreMedicationRequest $request): JsonResponse
    {
        $medication = Medication::create($request->validated());

        return (new MedicationResource($medication))->response()
            ->setStatusCode(201);
    }
}
