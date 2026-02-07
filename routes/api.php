<?php

use App\Http\Controllers\PictureController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Picture upload routes
Route::prefix('parties/{party}')->group(function () {
    Route::get('/pictures', [PictureController::class, 'index']);
    Route::post('/pictures', [PictureController::class, 'store']);
    Route::delete('/pictures/{picture}', [PictureController::class, 'destroy']);
});
