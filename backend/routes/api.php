<?php

use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PlayerHandController;
use Illuminate\Support\Facades\Route;

Route::post('/games', [GameController::class, 'store']);
Route::get('/games/{game}', [GameController::class, 'show']);
Route::post('/players/{player}/hand', [PlayerHandController::class, 'store']);
