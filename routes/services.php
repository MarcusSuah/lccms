<?php

use App\Http\Controllers\Admin\Facilities\FacilityController;
use App\Http\Controllers\Admin\Facilities\NeedsAssessmentController;
use App\Http\Controllers\Admin\Infrastructure\DevelopmentProjectController;
use App\Http\Controllers\Admin\Infrastructure\InfrastructureAssetController;
use App\Http\Controllers\Admin\Leadership\LeadershipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Facilities, leadership, infrastructure and development projects
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'strong-auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('facilities', FacilityController::class)->except(['destroy']);
    Route::post('facilities/{facility}/inspections', [FacilityController::class, 'inspect'])->name('facilities.inspections.store');

    Route::get('needs-assessment', [NeedsAssessmentController::class, 'index'])->name('needs-assessment.index');
    Route::put('needs-assessment/{community}', [NeedsAssessmentController::class, 'update'])->name('needs-assessment.update');

    Route::get('leadership/positions', [LeadershipController::class, 'positions'])->name('leadership.positions.index');
    Route::post('leadership/positions', [LeadershipController::class, 'savePosition'])->name('leadership.positions.store');
    Route::put('leadership/positions/{position}', [LeadershipController::class, 'savePosition'])->name('leadership.positions.update');
    Route::get('leadership/{tenure}/document', [LeadershipController::class, 'document'])->name('leadership.document');
    Route::resource('leadership', LeadershipController::class)->parameters(['leadership' => 'tenure'])->except(['show', 'destroy']);

    Route::resource('infrastructure', InfrastructureAssetController::class)->parameters(['infrastructure' => 'asset'])->except(['show', 'destroy']);

    Route::resource('projects', DevelopmentProjectController::class)->except(['destroy']);
    Route::post('projects/{project}/milestones', [DevelopmentProjectController::class, 'storeMilestone'])->name('projects.milestones.store');
    Route::patch('projects/{project}/milestones/{milestone}', [DevelopmentProjectController::class, 'updateMilestone'])->name('projects.milestones.update');
    Route::post('projects/{project}/reports', [DevelopmentProjectController::class, 'storeReport'])->name('projects.reports.store');
});
