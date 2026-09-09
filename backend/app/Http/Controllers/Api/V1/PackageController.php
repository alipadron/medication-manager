<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    /**
     * Display a listing of the packages, optionally filtered by medication.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = Package::latest();

        if ($medicationId = request()->query('medication_id')) {
            $query->where('medication_id', $medicationId);
        }

        return PackageResource::collection($query->get());
    }

    /**
     * Store a newly created package in storage.
     */
    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = Package::create($request->validated());

        return (new PackageResource($package))->response()
            ->setStatusCode(201);
    }

    /**
     * Update the package's remaining doses in storage (consumption or a
     * stock correction; the medication a package supplies never changes).
     */
    public function update(UpdatePackageRequest $request, Package $package): PackageResource
    {
        $package->update($request->validated());

        return new PackageResource($package);
    }
}
