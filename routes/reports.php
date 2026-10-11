<?php

use App\Http\Controllers\Admin\Maps\MapController;
use App\Http\Controllers\Admin\Reports\ExportController;
use App\Http\Controllers\Admin\Reports\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports, exports and maps
|--------------------------------------------------------------------------
| Report definitions decide who may see which rows; exports are queued,
| private to the requester, logged and deleted after the retention period.
*/

Route::middleware(['auth', 'verified', 'strong-auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/exports', [ExportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('reports.exports.store');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    Route::get('map', [MapController::class, 'index'])->name('map.index');
    Route::get('map/points', [MapController::class, 'points'])->name('map.points');
});
