<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController; 
use App\Http\Controllers\AuthController;
Route::get('/channels', [ChannelController::class, 'index']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/verify', [AuthController::class, 'verify']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

