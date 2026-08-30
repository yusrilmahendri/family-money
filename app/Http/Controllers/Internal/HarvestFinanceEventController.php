<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StoreHarvestFinanceEventRequest;
use App\Services\HarvestReceivableSyncService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;

class HarvestFinanceEventController extends Controller
{
    public function __construct(private readonly HarvestReceivableSyncService $sync) {}

    public function store(StoreHarvestFinanceEventRequest $request): JsonResponse
    {
        try {
            $result = $this->sync->handle($request->payload());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
