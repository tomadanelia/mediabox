<?php
use App\Http\Controllers\InterPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController; 
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\AdminCategoryController;
Route::prefix('channels')->group(function () {
    Route::get('/', [ChannelController::class, 'getChannelFacade']);
    Route::get('/categories', [ChannelController::class, 'getCategories']);
    Route::get('/{id}/stream', [ChannelController::class, 'getStreamUrl']); 
    Route::get('/{id}/programs', [ChannelController::class, 'programs']);
    Route::get('/{id}/programs/all',[ChannelController::class, 'allPrograms']);
    Route::get('/{id}/archive', [ChannelController::class, 'archive']);
});
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:3,1');

    Route::post('/login/verify', [AuthController::class, 'verifyLogin'])
        ->middleware('throttle:5,1');

    Route::post('/verify', [AuthController::class, 'verify'])
        ->middleware('throttle:3,1');

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/resend', [AuthController::class, 'resendCode'])
        ->middleware('throttle:3,1');
});
Route::get('/interpay/balance', [InterPayController::class, 'handle']);   
Route::post('/interpay/balance', [InterPayController::class, 'handle']);  
Route::get('/plans', [SubscriptionController::class, 'index']);
Route::get('/plans/{planId}/channels', [SubscriptionController::class, 'getChannelsForPlan']);
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
    Route::get('/admin/dashboard', [AdminCategoryController::class, 'dashboard'])->middleware('auth:sanctum');
});
Route::prefix('admin')->group(function () {

    Route::prefix('categories')->group(function () {
        Route::post('/', [AdminCategoryController::class, 'addCategories']);
        Route::get('/{categoryId}', [AdminCategoryController::class, 'getChannelsForCategory']);
        Route::post('/{categoryId}', [AdminCategoryController::class, 'assignChannelsToCategory']);
        Route::put('/{categoryId}', [AdminCategoryController::class, 'editCategory']);
        Route::delete('/{categoryId}', [AdminCategoryController::class, 'removeCategory']);
    });

    Route::prefix('plans')->group(function () {
        Route::post('/', [AdminPlansController::class, 'addPlan']);
        Route::put('/{planId}', [AdminPlansController::class, 'editPlan']);
        Route::post('/{planId}/disable', [AdminPlansController::class, 'disablePlan']);
        Route::post('/{planId}/channels', [AdminPlansController::class, 'addChannelsToPlan']);
    });

});
