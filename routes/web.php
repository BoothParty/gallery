<?php

use App\Http\Controllers\Admin\AudiobookController as AdminAudiobookController;
use App\Http\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Admin\PartyController as AdminPartyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AudiobookController;
use App\Http\Controllers\AudioGreetingController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyDashboardController;
use App\Http\Controllers\PartyMetadataController;
use App\Http\Controllers\PictureDownloadController;
use App\Http\Controllers\PictureFileController;
use App\Http\Controllers\PictureVisibilityController;
use App\Http\Middleware\ResolvePartyHost;
use Illuminate\Support\Facades\Route;

/**
 * The callback provisions a user on first login, so the three of them carry a
 * ceiling. Keyed by address alone, unlike the party routes: staff logging in
 * do not share a venue's wifi.
 */
Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('login', [OAuthController::class, 'redirect'])->name('login');
    Route::get('auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('auth.redirect');
    Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])->name('auth.callback');
});
Route::post('logout', [OAuthController::class, 'destroy'])->name('logout');

/**
 * The client's audio space, addressed by the party's private uuid rather than
 * by a subdomain: only the events sold as a published gallery get one of
 * those. Declared ahead of the `{party}` group, which is a catch-all prefix.
 */
Route::get('audiobook/{party:client_uuid}', [AudiobookController::class, 'show'])
    ->middleware(['audiobook', 'throttle:audiobook-entry'])
    ->name('audiobook.show');

Route::post('audiobook/{party:client_uuid}/annonce', [AudioGreetingController::class, 'store'])
    ->middleware(['audiobook', 'throttle:audiobook-write'])
    ->name('audiobook.greeting.store');

Route::post('audiobook/{party:client_uuid}/annonce/soumettre', [AudioGreetingController::class, 'submit'])
    ->middleware(['audiobook', 'throttle:audiobook-write'])
    ->name('audiobook.greeting.submit');

Route::prefix('{party}')->name('party.')->group(function () {
    Route::get('/', [PartyController::class, 'show'])
        ->middleware(ResolvePartyHost::class)
        ->name('gallery');

    Route::post('download', PictureDownloadController::class)
        ->middleware('throttle:party-download')
        ->name('download');

    Route::post('metadata', PartyMetadataController::class)
        ->middleware(['party.host', 'throttle:party-metadata'])
        ->name('metadata.update');

    Route::get('pictures/{picture}', PictureFileController::class)
        ->scopeBindings()
        ->middleware('throttle:party-picture')
        ->name('pictures.show');

    Route::patch('pictures/{picture}/visibility', PictureVisibilityController::class)
        ->scopeBindings()
        ->middleware(['party.host', 'throttle:party-metadata'])
        ->name('pictures.visibility');

    Route::prefix('dashboard')->name('dashboard.')->middleware('auth')->group(function () {
        Route::get('/', [PartyDashboardController::class, 'show'])->name('show');

        Route::get('/pictures', [PartyDashboardController::class, 'pictures'])->name('pictures');
        Route::post('/pictures', [AdminGalleryController::class, 'store'])->name('pictures.store');
        Route::delete('/pictures', [AdminGalleryController::class, 'bulkDelete'])->name('pictures.bulk-delete');
    });
});

/**
 * Every administration route binds a party by its identifier rather than by
 * its name: only the events published on a subdomain of their own carry a
 * name, and a party sold with the audio guestbook alone has none. Binding on
 * the name here would make those parties unreachable — unmodifiable and
 * undeletable — from the administration.
 */
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('livre-or', [AdminAudiobookController::class, 'queue'])->name('audiobook.queue');
    Route::get('livre-or/{party:id}', [AdminAudiobookController::class, 'show'])->name('audiobook.show');
    Route::post('livre-or/{party:id}/annonce/valider', [AdminAudiobookController::class, 'approve'])
        ->name('audiobook.approve');
    Route::post('livre-or/{party:id}/annonce/refuser', [AdminAudiobookController::class, 'reject'])
        ->name('audiobook.reject');

    Route::post('parties/bulk', [AdminPartyController::class, 'bulkStore'])->name('parties.bulk-store');
    Route::get('parties/{party:id}/poster', [AdminPartyController::class, 'poster'])->name('parties.poster');
    Route::post('parties/{party:id}/host-token', [AdminPartyController::class, 'regenerateHostToken'])
        ->name('parties.host-token');
    Route::patch('parties/{party:id}/modules', [AdminPartyController::class, 'updateModules'])
        ->name('parties.modules');

    Route::get('parties', [AdminPartyController::class, 'index'])->name('parties.index');
    Route::post('parties', [AdminPartyController::class, 'store'])->name('parties.store');
    Route::put('parties/{party:id}', [AdminPartyController::class, 'update'])->name('parties.update');
    Route::delete('parties/{party:id}', [AdminPartyController::class, 'destroy'])->name('parties.destroy');

    Route::resource('users', AdminUserController::class)->except(['show']);
});
