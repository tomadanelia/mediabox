<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController; 
use App\Http\Controllers\AuthController;
Route::get('/channels', [ChannelController::class, 'index']) ->middleware('throttle:60,1');
Route::post('/auth/login', [AuthController::class, 'login'])  ->middleware('throttle:3,1');
Route::post('/auth/verify', [AuthController::class, 'verify']) ->middleware('throttle:3,1');
Route::post('/auth/register', [AuthController::class, 'register']) ->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

