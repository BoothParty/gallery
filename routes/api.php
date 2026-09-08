<?php

use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\PartyStateController;
use App\Http\Controllers\Api\PictureController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('parties', PartyController::class);
    /**
     * `scoped()` so `{picture}` is resolved through the party's own pictures:
     * without it the id resolves globally and one party's URL reaches, and
     * deletes, another party's picture.
     */
    Route::apiResource('parties/{party}/pictures', PictureController::class)
        ->only(['index', 'store', 'show', 'destroy'])
        ->scoped();
});

/**
 * The probe the public gallery polls for changes.
 *
 * It lives here rather than in `web.php` on purpose: the `web` group runs
 * `StartSession`, which writes the database-backed session on every request.
 * With a phone per guest polling all evening, those writes would cost far
 * more than the aggregate this endpoint takes care to cache. The `api` group
 * is stateless and already carries `SubstituteBindings`, which the route needs
 * to resolve `{party}` by its name.
 */
Route::get('parties/{party}/state', PartyStateController::class)
    ->middleware('throttle:party-state')
    ->name('api.parties.state');
