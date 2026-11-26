<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\StoreUploadBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShiftsRequest;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    public function store(StoreShiftsRequest $request, StoreUploadBatch $storeBatch): JsonResponse
    {
        return new JsonResponse($storeBatch->handle($request->user(), $request, Shift::class), 201);
    }
}
