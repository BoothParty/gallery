<?php

use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\PictureController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('parties', PartyController::class);
    Route::apiResource('parties/{party}/pictures', PictureController::class)
        ->only(['index', 'store', 'show', 'destroy']);
});
