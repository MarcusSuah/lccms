<?php

use App\Http\Controllers\Field\FieldAppController;
use App\Http\Controllers\Field\ServiceWorkerController;
use Illuminate\Support\Facades\Route;

Route::get('field-sw.js', ServiceWorkerController::class)->name('field.service-worker');

/*
|--------------------------------------------------------------------------
| Field application
|--------------------------------------------------------------------------
| The shell is cached by the service worker so it opens without network.
| Device registration requires a signed-in, online web session.
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('field', [FieldAppController::class, 'show'])->name('field');
    Route::post('field/devices', [FieldAppController::class, 'registerDevice'])
        ->middleware('throttle:5,1')
        ->name('field.devices.store');
});
