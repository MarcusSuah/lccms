<?php

use App\Http\Controllers\Admin\Content\EventController as AdminEventController;
use App\Http\Controllers\Admin\Content\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\Content\PostController as AdminPostController;
use App\Http\Controllers\Admin\Content\SiteContentController;
use App\Http\Controllers\Public\AreaOptionsController;
use App\Http\Controllers\Public\MediaController;
use App\Http\Controllers\Public\PublicCommunityController;
use App\Http\Controllers\Public\PublicNewsController;
use App\Http\Controllers\Public\PublicSiteController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
| Reads only published, verified, public-marked content and approved
| aggregate statistics. Never individual census records.
*/

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('about', [PublicSiteController::class, 'about'])->name('about');
Route::get('map', [PublicSiteController::class, 'map'])->name('map');
Route::get('contact', [PublicSiteController::class, 'contact'])->name('contact');
Route::post('contact', [PublicSiteController::class, 'sendContact'])
    ->middleware(['throttle:5,10', ProtectAgainstSpam::class])
    ->name('contact.store');

Route::get('communities', [PublicCommunityController::class, 'index'])->name('communities.index');
Route::get('communities/{community:slug}', [PublicCommunityController::class, 'show'])->name('communities.show');
Route::get('leaders', [PublicCommunityController::class, 'leaders'])->name('leaders');

Route::get('news', [PublicNewsController::class, 'index'])->name('news.index');
Route::get('news/{post:slug}', [PublicNewsController::class, 'show'])->name('news.show');
Route::get('events', [PublicNewsController::class, 'events'])->name('events.index');
Route::get('events/{event:slug}', [PublicNewsController::class, 'event'])->name('events.show');

Route::get('area-options', AreaOptionsController::class)->middleware('throttle:120,1')->name('area-options');

Route::get('media/{media}/{variant?}', MediaController::class)
    ->where('variant', 'full|thumb')
    ->middleware('throttle:240,1')
    ->name('media.show');

/*
|--------------------------------------------------------------------------
| Content administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'strong-auth'])->prefix('admin/content')->name('admin.content.')->group(function () {
    Route::get('media', [AdminMediaController::class, 'index'])->name('media.index');
    Route::post('media', [AdminMediaController::class, 'store'])->middleware('throttle:60,1')->name('media.store');
    Route::put('media/{media}', [AdminMediaController::class, 'update'])->name('media.update');
    Route::patch('media/{media}/approval', [AdminMediaController::class, 'approve'])->name('media.approve');
    Route::delete('media/{media}', [AdminMediaController::class, 'destroy'])->name('media.destroy');

    Route::resource('posts', AdminPostController::class)->except(['show', 'destroy']);
    Route::patch('posts/{post}/status', [AdminPostController::class, 'transition'])->name('posts.status');

    Route::resource('events', AdminEventController::class)->except(['show', 'destroy']);
    Route::patch('events/{event}/status', [AdminEventController::class, 'status'])->name('events.status');

    Route::get('announcements', [SiteContentController::class, 'announcements'])->name('announcements.index');
    Route::post('announcements', [SiteContentController::class, 'saveAnnouncement'])->name('announcements.store');
    Route::put('announcements/{announcement}', [SiteContentController::class, 'saveAnnouncement'])->name('announcements.update');

    Route::get('pages', [SiteContentController::class, 'pages'])->name('pages.index');
    Route::put('pages/{slug}', [SiteContentController::class, 'savePage'])->name('pages.update');

    Route::get('inbox', [SiteContentController::class, 'inbox'])->name('inbox.index');
    Route::patch('inbox/{message}', [SiteContentController::class, 'updateMessage'])->name('inbox.update');
});
