<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HoldExpiredException;
use App\Exceptions\IdempotencyKeyNonUniqueException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SlotNotAvailableException;
use App\Http\Requests\HoldCreateRequest;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;

class HoldController extends Controller
{
    public function __construct(private SlotService $slotService)
    {
    }

    public function create(int $slotId, HoldCreateRequest $request): JsonResponse
    {
        try {
            $hold = $this->slotService->createHold($slotId, $request->header('Idempotency-Key'));
        } catch (IdempotencyKeyNonUniqueException|SlotNotAvailableException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], $exception->getCode());
        } catch (NotFoundException) {
            return new JsonResponse(['error' => 'Холд не найден'], 404);
        }

        return new JsonResponse($hold, JsonResponse::HTTP_CREATED);
    }

    public function confirm(int $holdId): JsonResponse
    {
        try {
            $hold = $this->slotService->confirmHold($holdId);
        } catch (HoldExpiredException|SlotNotAvailableException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], $exception->getCode());
        } catch (NotFoundException) {
            return new JsonResponse(['error' => 'Холд не найден'], 404);
        }

        return response()->json(['hold' => $hold]);
    }

    public function cancel(int $holdId): JsonResponse
    {
        try {
            $hold = $this->slotService->cancelHold($holdId);
        } catch (NotFoundException) {
            return new JsonResponse(['error' => 'Холд не найден'], 404);
        }

        return response()->json(['hold' => $hold]);
    }
}
