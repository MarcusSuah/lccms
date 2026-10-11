<?php

use App\Http\Controllers\Admin\Access\UserController;
use App\Http\Controllers\Admin\Access\UserInvitationController;
use App\Http\Controllers\Admin\Geography\GeoAreaController;
use App\Http\Controllers\Admin\Geography\GeographyImportController;
use App\Http\Controllers\Admin\Geography\GeoOptionsController;
use App\Http\Controllers\Admin\Geography\GeoReassignmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff administration
|--------------------------------------------------------------------------
| Every route requires a verified, active account. Privileged roles must also
| have two-factor authentication or a passkey. Each controller authorizes the
| action and its geographic scope; route middleware is not the security boundary.
*/

Route::middleware(['auth', 'verified', 'strong-auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('users/{user}/invitation', [UserInvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('users.invitation');

    Route::prefix('geography')->name('geography.')->group(function () {
        Route::get('options', GeoOptionsController::class)->name('options');

        Route::get('imports', [GeographyImportController::class, 'index'])->name('imports.index');
        Route::get('imports/template', [GeographyImportController::class, 'template'])->name('imports.template');
        Route::post('imports', [GeographyImportController::class, 'store'])->middleware('throttle:10,1')->name('imports.store');
        Route::get('imports/{import}', [GeographyImportController::class, 'show'])->name('imports.show');
        Route::patch('imports/{import}', [GeographyImportController::class, 'update'])->name('imports.update');

        Route::get('reassignments', [GeoReassignmentController::class, 'index'])->name('reassignments.index');
        Route::patch('reassignments/{reassignment}', [GeoReassignmentController::class, 'update'])->name('reassignments.update');

        Route::get('{level}', [GeoAreaController::class, 'index'])->name('index');
        Route::get('{level}/create', [GeoAreaController::class, 'create'])->name('create');
        Route::post('{level}', [GeoAreaController::class, 'store'])->name('store');
        Route::get('{level}/{geoArea}', [GeoAreaController::class, 'show'])->name('show');
        Route::get('{level}/{geoArea}/edit', [GeoAreaController::class, 'edit'])->name('edit');
        Route::put('{level}/{geoArea}', [GeoAreaController::class, 'update'])->name('update');
        Route::post('{level}/{geoArea}/reassignments', [GeoReassignmentController::class, 'store'])->name('reassignments.store');
    });
});
