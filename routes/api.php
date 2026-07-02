<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\HoldController;
use Illuminate\Support\Facades\Route;

Route::get('/slots/availability', [AvailabilityController::class, 'availableSlots']);
Route::post('/slots/{id}/hold', [HoldController::class, 'create'])->whereNumber('id');

Route::prefix('holds')->group(function () {
    Route::post('/{id}/confirm', [HoldController::class, 'confirm'])->whereNumber('id');
    Route::delete('/{id}', [HoldController::class, 'cancel'])->whereNumber('id');
});
