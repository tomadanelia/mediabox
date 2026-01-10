<?php
use App\Http\Controllers\InterPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController; 
use App\Http\Controllers\AuthController;
Route::prefix('channels')->group(function () {
    Route::get('/', [ChannelController::class, 'index']);
    Route::get('/{id}/stream', [ChannelController::class, 'stream']); 
    Route::get('/{id}/programs', [ChannelController::class, 'programs']);
    Route::get('/{id}/archive', [ChannelController::class, 'archive']);
});
Route::post('/auth/login', [AuthController::class, 'login'])  ->middleware('throttle:3,1');
Route::post('/auth/verify', [AuthController::class, 'verify']) ->middleware('throttle:3,1');
Route::post('/auth/register', [AuthController::class, 'register']) ->middleware('throttle:5,1');
Route::post('/auth/resend', [AuthController::class, 'resendCode'])->middleware('throttle:3,1');
Route::post('/interpay/balance', [InterPayController::class, 'handle']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

