<?php

use App\Http\Controllers\Admin\Census\CampaignAssignmentController;
use App\Http\Controllers\Admin\Census\CampaignController;
use App\Http\Controllers\Admin\Census\CampaignStatusController;
use App\Http\Controllers\Admin\Census\CorrectionController;
use App\Http\Controllers\Admin\Census\DuplicateFlagController;
use App\Http\Controllers\Admin\Census\HouseholdController;
use App\Http\Controllers\Admin\Census\HouseholdReviewController;
use App\Http\Controllers\Admin\Census\PopulationReportController;
use App\Http\Controllers\Admin\Sync\DeviceController;
use App\Http\Controllers\Admin\Sync\SyncConflictController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Census administration and review
|--------------------------------------------------------------------------
| Data entry happens in the field application (offline-capable). These pages
| plan campaigns, assign work, review submissions and report results.
*/

Route::middleware(['auth', 'verified', 'strong-auth'])->prefix('admin/census')->name('admin.census.')->group(function () {
    Route::resource('campaigns', CampaignController::class)->except(['destroy']);
    Route::patch('campaigns/{campaign}/status', [CampaignStatusController::class, 'update'])->name('campaigns.status');
    Route::get('campaigns/{campaign}/assignment-candidates', [CampaignAssignmentController::class, 'candidates'])->name('campaigns.assignments.candidates');
    Route::post('campaigns/{campaign}/assignments', [CampaignAssignmentController::class, 'store'])->name('campaigns.assignments.store');
    Route::delete('campaigns/{campaign}/assignments/{assignment}', [CampaignAssignmentController::class, 'destroy'])->name('campaigns.assignments.destroy');

    Route::get('households', [HouseholdController::class, 'index'])->name('households.index');
    Route::get('households/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::post('households/{household}/reviews', [HouseholdReviewController::class, 'store'])->name('households.reviews.store');

    Route::get('corrections', [CorrectionController::class, 'index'])->name('corrections.index');
    Route::post('corrections', [CorrectionController::class, 'store'])->name('corrections.store');
    Route::patch('corrections/{correction}', [CorrectionController::class, 'update'])->name('corrections.update');

    Route::get('duplicates', [DuplicateFlagController::class, 'index'])->name('duplicates.index');
    Route::patch('duplicates/{flag}', [DuplicateFlagController::class, 'update'])->name('duplicates.update');

    Route::get('population', PopulationReportController::class)->name('population');
});

Route::middleware(['auth', 'verified', 'strong-auth'])->prefix('admin/sync')->name('admin.sync.')->group(function () {
    Route::get('conflicts', [SyncConflictController::class, 'index'])->name('conflicts.index');
    Route::patch('conflicts/{conflict}', [SyncConflictController::class, 'update'])->name('conflicts.update');
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
});
