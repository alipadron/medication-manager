<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHomeInventoryEntryRequest;
use App\Http\Resources\HomeInventoryEntryResource;
use App\Models\HomeInventoryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HomeInventoryController extends Controller
{
    /**
     * Display a listing of the leftover-medication entries.
     */
    public function index(): AnonymousResourceCollection
    {
        return HomeInventoryEntryResource::collection(HomeInventoryEntry::latest()->get());
    }

    /**
     * Store a newly created home inventory entry in storage.
     */
    public function store(StoreHomeInventoryEntryRequest $request): JsonResponse
    {
        $entry = HomeInventoryEntry::create($request->validated());

        return (new HomeInventoryEntryResource($entry))->response()
            ->setStatusCode(201);
    }
}
