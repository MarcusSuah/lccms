<?php

use App\Http\Controllers\Admin\Complaints\ComplaintController as AdminComplaintController;
use App\Http\Controllers\Public\ComplaintController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
|--------------------------------------------------------------------------
| Complaints
|--------------------------------------------------------------------------
| Public submission needs no account. Tracking uses a 48-character random
| token; complaint pages are never addressed by a sequential id.
*/

Route::get('complaints/new', [ComplaintController::class, 'create'])->name('complaints.create');
Route::post('complaints', [ComplaintController::class, 'store'])
    ->middleware(['throttle:5,10', ProtectAgainstSpam::class])
    ->name('complaints.store');
Route::get('complaints/submitted', [ComplaintController::class, 'submitted'])->name('complaints.submitted');
Route::get('complaints/track/{token}', [ComplaintController::class, 'track'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:30,1')
    ->name('complaints.track');

Route::middleware(['auth', 'verified', 'strong-auth'])->prefix('admin/complaints')->name('admin.complaints.')->group(function () {
    Route::get('/', [AdminComplaintController::class, 'index'])->name('index');
    Route::get('{complaint}', [AdminComplaintController::class, 'show'])->name('show');
    Route::patch('{complaint}/status', [AdminComplaintController::class, 'update'])->name('status');
    Route::patch('{complaint}/assignment', [AdminComplaintController::class, 'assign'])->name('assign');
    Route::post('{complaint}/notes', [AdminComplaintController::class, 'note'])->name('notes.store');
    Route::get('{complaint}/attachments/{slot}/{variant?}', [AdminComplaintController::class, 'attachment'])
        ->whereNumber('slot')
        ->where('variant', 'full|thumb')
        ->name('attachments.show');
});
