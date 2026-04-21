<?php

use App\Http\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Admin\PartyController as AdminPartyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('{party}')->name('party.')->group(function () {
    Route::get('/', [PartyController::class, 'show'])->name('gallery');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [PartyDashboardController::class, 'show'])->name('show');

        Route::get('/pictures', [PartyDashboardController::class, 'pictures'])->name('pictures');
        Route::post('/pictures', [AdminGalleryController::class, 'store'])->name('pictures.store');
        Route::delete('/pictures', [AdminGalleryController::class, 'bulkDelete'])->name('pictures.bulk-delete');
        Route::post('/media/{media_type}/enable', [PartyDashboardController::class, 'enableMedia'])
            ->whereIn('media_type', ['pictures', 'audios'])
            ->name('media.enable');
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('parties', AdminPartyController::class)->except(['show', 'create']);
    Route::resource('users', AdminUserController::class)->except(['show']);
});
