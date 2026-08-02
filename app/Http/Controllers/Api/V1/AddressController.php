<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Account\SaveAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Http\Resources\V1\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class AddressController extends Controller
{
    public function __construct(private readonly SaveAddress $saveAddress)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($request->user()->addresses()->latest()->get());
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $address = $this->saveAddress->handle($request->user(), $request->validated());

        return response()->json(['data' => new AddressResource($address)], 201);
    }

    public function update(AddressRequest $request, Address $address): JsonResponse
    {
        Gate::authorize('update', $address);

        $address = $this->saveAddress->handle($request->user(), $request->validated(), $address);

        return response()->json(['data' => new AddressResource($address)]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        Gate::authorize('delete', $address);

        $address->delete();

        return response()->json(['message' => 'Address deleted.']);
    }
}
