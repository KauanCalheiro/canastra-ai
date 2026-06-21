<?php

use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PlayerHandController;
use App\Http\Controllers\Api\SequenceController;
use Illuminate\Support\Facades\Route;

Route::post('/games', [GameController::class, 'store']);
Route::get('/games/{game}', [GameController::class, 'show']);
Route::post('/players/{player}/hand', [PlayerHandController::class, 'store']);
Route::post('/games/{game}/sequences', [SequenceController::class, 'store']);
Route::post('/sequences/{sequence}/cards', [SequenceController::class, 'extend']);
