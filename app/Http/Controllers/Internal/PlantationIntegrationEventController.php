<?php

namespace App\Http\Controllers\Internal;

use App\Exceptions\IntegrationDependencyNotReadyException;
use App\Exceptions\IntegrationIntegrityConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StorePlantationIntegrationEventRequest;
use App\Services\PlantationIntegrationEventService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;

class PlantationIntegrationEventController extends Controller
{
    public function __construct(private readonly PlantationIntegrationEventService $events) {}

    public function store(StorePlantationIntegrationEventRequest $request): JsonResponse
    {
        try {
            $result = $this->events->ingest($request->validated());
        } catch (IntegrationDependencyNotReadyException $exception) {
            return response()->json([
                'ok' => false,
                'code' => 'DEPENDENCY_NOT_READY',
                'message' => $exception->getMessage(),
            ], 409);
        } catch (IntegrationIntegrityConflictException $exception) {
            return response()->json([
                'ok' => false,
                'code' => 'INTEGRITY_CONFLICT',
                'message' => $exception->getMessage(),
            ], 409);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }
}
