<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonController extends Controller
{
    /**
     * Display a listing of the people.
     */
    public function index(): AnonymousResourceCollection
    {
        return PersonResource::collection(Person::latest()->get());
    }

    /**
     * Store a newly created person in storage.
     */
    public function store(StorePersonRequest $request): JsonResponse
    {
        $person = Person::create($request->validated());

        return (new PersonResource($person))->response()
            ->setStatusCode(201);
    }
}
