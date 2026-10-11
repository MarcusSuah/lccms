<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/public.php';
require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/census.php';
require __DIR__.'/field.php';
require __DIR__.'/services.php';
require __DIR__.'/complaints.php';
require __DIR__.'/reports.php';
