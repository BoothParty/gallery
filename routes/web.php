<?php

use App\Http\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Admin\PartyController as AdminPartyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyDashboardController;
use App\Http\Controllers\PartyMetadataController;
use App\Http\Controllers\PictureDownloadController;
use App\Http\Controllers\PictureFileController;
use App\Http\Middleware\ResolvePartyHost;
use Illuminate\Support\Facades\Route;

Route::get('login', [OAuthController::class, 'redirect'])->name('login');
Route::get('auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('auth.redirect');
Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])->name('auth.callback');
Route::post('logout', [OAuthController::class, 'destroy'])->name('logout');

Route::prefix('{party}')->name('party.')->group(function () {
    Route::get('/', [PartyController::class, 'show'])
        ->middleware(ResolvePartyHost::class)
        ->name('gallery');

    Route::post('download', PictureDownloadController::class)
        ->middleware('throttle:party-download')
        ->name('download');

    Route::put('metadata', PartyMetadataController::class)
        ->middleware(['party.host', 'throttle:party-metadata'])
        ->name('metadata.update');

    Route::get('pictures/{picture}', PictureFileController::class)
        ->scopeBindings()
        ->middleware('throttle:party-picture')
        ->name('pictures.show');

    Route::prefix('dashboard')->name('dashboard.')->middleware('auth')->group(function () {
        Route::get('/', [PartyDashboardController::class, 'show'])->name('show');

        Route::get('/pictures', [PartyDashboardController::class, 'pictures'])->name('pictures');
        Route::post('/pictures', [AdminGalleryController::class, 'store'])->name('pictures.store');
        Route::delete('/pictures', [AdminGalleryController::class, 'bulkDelete'])->name('pictures.bulk-delete');
    });
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::post('parties/bulk', [AdminPartyController::class, 'bulkStore'])->name('parties.bulk-store');
    Route::get('parties/{party}/poster', [AdminPartyController::class, 'poster'])->name('parties.poster');
    Route::post('parties/{party}/host-token', [AdminPartyController::class, 'regenerateHostToken'])
        ->name('parties.host-token');
    Route::resource('parties', AdminPartyController::class)->except(['show', 'create', 'edit']);
    Route::resource('users', AdminUserController::class)->except(['show']);
});
