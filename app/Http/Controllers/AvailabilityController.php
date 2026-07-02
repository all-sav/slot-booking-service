<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Slot;
use App\Services\SlotService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AvailabilityController extends Controller
{
    public function __construct(private SlotService $slotService)
    {
    }

    public function availableSlots(): JsonResponse
    {
        try {
            $slots = $this->slotService->getAvailableSlots();
        } catch (LockTimeoutException) {
            return new JsonResponse(['message' => 'Попробуйте позже'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse($slots->map(static fn (Slot $slot) => [
            'slot_id' => $slot->id,
            'capacity' => $slot->capacity,
            'remaining' => $slot->remaining,
        ]), Response::HTTP_OK);
    }
}
