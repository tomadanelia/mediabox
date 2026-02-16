<?php
use App\Http\Controllers\InterPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController; 
use App\Http\Controllers\AuthController;
Route::prefix('channels')->group(function () {
    Route::get('/', [ChannelController::class, 'getChannelFacade']);
    Route::get('/{id}/stream', [ChannelController::class, 'getStreamUrl']); 
    Route::get('/{id}/programs', [ChannelController::class, 'programs']);
    Route::get('/{id}/programs/all',[ChannelController::class, 'allPrograms']);
    Route::get('/{id}/archive', [ChannelController::class, 'archive']);
});
Route::post('/auth/login', [AuthController::class, 'login'])  ->middleware('throttle:3,1');
Route::post('/auth/login/verify', [AuthController::class, 'verifyLogin'])->middleware('throttle:5,1');
Route::post('/auth/verify', [AuthController::class, 'verify']) ->middleware('throttle:3,1');
Route::post('/auth/register', [AuthController::class, 'register']) ->middleware('throttle:5,1');
Route::post('/auth/resend', [AuthController::class, 'resendCode'])->middleware('throttle:3,1');
Route::get('/interpay/balance', [InterPayController::class, 'handle']);   
Route::post('/interpay/balance', [InterPayController::class, 'handle']);  
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/plans', [SubscriptionController::class, 'index']);
    Route::post('/plans/purchase', [SubscriptionController::class, 'purchase']);
    Route::get('/channels/heartbeat', [ChannelController::class, 'heartbeat']);
    Route::get('/user/preferences/favourite-channels', [UserPreferencesController::class, 'getFavouriteChannels']);
    Route::post('/user/preferences/favourite-channels', [UserPreferencesController::class, 'addFavouriteChannel']);
    Route::delete('/user/preferences/favourites/{channelId}', [UserPreferencesController::class, 'removeFavouriteChannel']);
});

