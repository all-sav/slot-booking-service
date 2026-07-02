<?php

declare(strict_types=1);

namespace App\Services;

use App\Dictionary\HoldStatusEnum;
use App\Exceptions\HoldExpiredException;
use App\Exceptions\IdempotencyKeyNonUniqueException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SlotNotAvailableException;
use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlotService
{
    private const string CACHE_KEY = 'available_slots';

    private const int CACHE_TTL = 10;
    private const int LOCK_TTL = 5;
    private const int WAIT_TTL = 3;

    public function getAvailableSlots(): Collection
    {
        $slots = Cache::get(self::CACHE_KEY);
        if ($slots !== null) {
            return $slots;
        }

        $lock = Cache::lock('lock:' . self::CACHE_KEY, self::LOCK_TTL);

        try {
            return $lock->block(self::WAIT_TTL, function () {
                $slots = Cache::get(self::CACHE_KEY);
                if ($slots !== null) {
                    return $slots;
                }

                $slots = Slot::all();

                Cache::put(self::CACHE_KEY, $slots, self::CACHE_TTL);

                return $slots;
            });
        } catch (LockTimeoutException $exception) {
            Log::error('Таймаут ожидания блокировки', ['error' => $exception->getMessage()]);

            throw $exception;
        }
    }

    /**
     * @throws IdempotencyKeyNonUniqueException | NotFoundException | SlotNotAvailableException
     */
    public function createHold(int $slotId, string $idempotencyKey): Hold
    {
        return DB::transaction(function () use ($slotId, $idempotencyKey) {
            $hold = Hold::query()
                ->where('idempotency_key', '=', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($hold !== null) {
                if ($hold->slot_id !== $slotId) {
                    throw new IdempotencyKeyNonUniqueException();
                }

                if ($this->isHoldIdempotency($hold)) {
                    return $hold;
                }

                $hold->delete();
            }

            $slot = Slot::find($slotId);

            if ($slot === null) {
                throw NotFoundException::notFound(Slot::class);
            }

            if (!$slot->hasAvailability()) {
                throw new SlotNotAvailableException();
            }


            return Hold::create([
                'slot_id' => $slot->id,
                'idempotency_key' => $idempotencyKey,
                'status' => HoldStatusEnum::HELD,
            ]);
        });
    }

    /**
     * @throws NotFoundException | HoldExpiredException | SlotNotAvailableException
     */
    public function confirmHold(int $holdId): Hold
    {
        return DB::transaction(function () use ($holdId) {
            $hold = Hold::query()
                ->where('id', '=', $holdId)
                ->where('status', '=', HoldStatusEnum::HELD)
                ->lockForUpdate()
                ->first();

            if ($hold === null) {
                throw NotFoundException::notFound(Hold::class);
            }

            if ($hold->isExpired()) {
                throw new HoldExpiredException(sprintf('Hold %d expired', $holdId));
            }

            $updated = Slot::query()
                ->where('id', '=', $hold->slot_id)
                ->where('remaining', '>', 0)
                ->decrement('remaining');

            if (!$updated) {
                throw new SlotNotAvailableException('Слот недоступен');
            }

            $hold->update(['status' => HoldStatusEnum::CONFIRMED]);

            $this->invalidateCache();

            return $hold;
        });
    }

    /**
     * @throws NotFoundException
     */
    public function cancelHold(int $holdId): Hold
    {
        return DB::transaction(function () use ($holdId) {
            $hold = Hold::query()
                ->where('id', '=', $holdId)
                ->where('status', '=', HoldStatusEnum::CONFIRMED)
                ->lockForUpdate()
                ->first();

            if ($hold === null) {
                throw NotFoundException::notFound(Hold::class);
            }

            $hold->update(['status' => HoldStatusEnum::CANCELLED]);

            Slot::query()
                ->where('id', '=', $hold->slot_id)
                ->increment('remaining');

            $this->invalidateCache();

            return $hold;
        });
    }

    private function isHoldIdempotency(Hold $hold): bool
    {
        if ($hold->status !== HoldStatusEnum::HELD) {
            return true;
        }

        return !$hold->isExpired();
    }

    private function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
