<?php
use App\Http\Controllers\InterPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController; 
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\AdminController;
Route::prefix('channels')->group(function () {
    Route::get('/', [ChannelController::class, 'getChannelFacade']);
    Route::get('/categories', [ChannelController::class, 'getCategories']);
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
Route::get('/plans', [SubscriptionController::class, 'index']);
Route::get('/internal/stream-auth', [ChannelController::class, 'streamAuth'])->middleware('whitelist.ip');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user()->load('account');
    });
    Route::post('/plans/purchase', [SubscriptionController::class, 'purchase']);
    Route::get('/plans/my', [SubscriptionController::class, 'myPlans']);
    Route::get('/channels/heartbeat', [ChannelController::class, 'heartbeat']);
    Route::get('/user/preferences/favourite-channels', [UserPreferencesController::class, 'getFavouriteChannels']);
    Route::post('/user/preferences/favourite-channels', [UserPreferencesController::class, 'addFavouriteChannel']);
    Route::delete('/user/preferences/favourites/{channelId}', [UserPreferencesController::class, 'removeFavouriteChannel']);
    Route::post('/user/preferences/watch', [UserPreferencesController::class, 'updateWatchHistory']);
    Route::get('/user/preferences/watch/last', [UserPreferencesController::class, 'getLastviewedChannels']);
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->middleware('auth:sanctum');
});
Route::post('/admin/categories', [AdminController::class, 'addCategories']);
Route::get('/admin/categories/{categoryId}', [AdminController::class, 'getChannelsForCategory']);
Route::post('/admin/categories/{categoryId}', [AdminController::class, 'assignChannelsToCategory']);
Route::put('/admin/categories/{categoryId}', [\App\Http\Controllers\AdminController::class, 'editCategory']);
Route::delete('/admin/categories/{categoryId}', [\App\Http\Controllers\AdminController::class, 'removeCategory']);