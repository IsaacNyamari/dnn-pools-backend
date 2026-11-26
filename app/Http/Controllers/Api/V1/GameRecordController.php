<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\StoreUploadBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGameRecordsRequest;
use App\Models\GameRecord;
use Illuminate\Http\JsonResponse;

class GameRecordController extends Controller
{
    public function store(StoreGameRecordsRequest $request, StoreUploadBatch $storeBatch): JsonResponse
    {
        return new JsonResponse($storeBatch->handle($request->user(), $request, GameRecord::class), 201);
    }
}
