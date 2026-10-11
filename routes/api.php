<?php

use App\Http\Controllers\Api\V1\Field\DeviceController;
use App\Http\Controllers\Api\V1\Field\FieldSessionController;
use App\Http\Controllers\Api\V1\Field\PackageController;
use App\Http\Controllers\Api\V1\Field\SyncBatchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Field enumeration API, version 1
|--------------------------------------------------------------------------
| Device-token authenticated. Every operation is re-validated, scope-checked
| and version-checked on the server; nothing sent by a device is trusted.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('devices/{uuid}/status', [DeviceController::class, 'status'])
        ->whereUuid('uuid')
        ->middleware('throttle:30,1')
        ->name('devices.status');

    Route::middleware(['auth:sanctum', 'field.device', 'throttle:field-sync'])->group(function () {
        Route::get('field/session', [FieldSessionController::class, 'show'])->name('field.session');
        Route::get('field/campaigns/{campaign}/package', [PackageController::class, 'show'])->name('field.package');
        Route::delete('field/device', [DeviceController::class, 'destroy'])->name('field.device.destroy');
        Route::get('sync/conflicts', [DeviceController::class, 'conflicts'])->name('sync.conflicts');
        Route::post('sync/batches', [SyncBatchController::class, 'store'])->name('sync.batches.store');
        Route::get('sync/batches/{batchUuid}', [SyncBatchController::class, 'show'])->whereUuid('batchUuid')->name('sync.batches.show');
    });
});
