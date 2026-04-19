<?php
use App\Http\Controllers\InterPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChannelController; 
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminPlansController;
use App\Http\Controllers\SpaAuthController;
use App\Http\Controllers\TvPairingController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\RemoteController;
use App\Http\Controllers\RadioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminChannelController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminDiscountController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\AdminRadioController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\AdminChannelUrlController;
Route::prefix('channels')->group(function () {
    Route::get('/', [ChannelController::class, 'getChannelFacade']);
    Route::get('/categories', [ChannelController::class, 'getCategories']);
    Route::get('/{id}/plans', [ChannelController::class, 'getChannelPlans']);
    Route::get('/{id}/programs', [ChannelController::class, 'programs']);
    Route::get('/{id}/programs/all',[ChannelController::class, 'allPrograms']);
    Route::get('/{id}/stream', [ChannelController::class, 'getStreamUrl']); 
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
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:3,1');
    Route::post('/resend', [AuthController::class, 'resendCode'])
        ->middleware('throttle:3,1');
    Route::prefix('web')->group(function () {
        Route::post('/verify', [SpaAuthController::class, 'verify'])
            ->middleware('throttle:3,1');
        Route::post('/login/verify', [SpaAuthController::class, 'verifyLogin'])
            ->middleware('throttle:5,1');
     });
});
Route::get('/interpay/balance', [InterPayController::class, 'handle']);   
Route::post('/interpay/balance', [InterPayController::class, 'handle']);  
Route::get('/plans', [SubscriptionController::class, 'index']);
Route::get('/plans/{planId}/channels', [SubscriptionController::class, 'getChannelsForPlan']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/web/logout', [SpaAuthController::class, 'logout']);
    Route::get('/user', [ProfileController::class, 'show']);
    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::post('/user/business-registration', [ProfileController::class, 'registerBusiness']);
    Route::get('/user/company', [ProfileController::class, 'getCompanyDetail']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('account');
    });
    Route::post('/plans/purchase', [SubscriptionController::class, 'purchase']);
    Route::get('/plans/my', [SubscriptionController::class, 'myPlans']);
    Route::post('/plans/tv-limit',[SubscriptionController::class,'upgradeTvLimit']);
    Route::get('/plans/tv-limit-price', [SubscriptionController::class, 'getTvLimitPrice']);
    Route::get('/tv/logged-in/devices',[SubscriptionController::class,'getTvDevices']);
    Route::post('/tv/free/device-slots', [SubscriptionController::class, 'logoutTvDevice']);
    Route::post('/tv/device-name',[ProfileController::class,'giveTvDeviceName']);
    Route::get('/tv/device/{device_id}', [ProfileController::class, 'getTvDeviceName']);
    Route::get('/user/transactions', [SubscriptionController::class, 'getTransactions']);
    Route::get('/user/preferences/favourite-channels', [UserPreferencesController::class, 'getFavouriteChannels']);
    Route::post('/user/preferences/favourite-channels', [UserPreferencesController::class, 'addFavouriteChannel']);
    Route::delete('/user/preferences/favourites/{channelId}', [UserPreferencesController::class, 'removeFavouriteChannel']);
    Route::get('/admin/dashboard', [AdminCategoryController::class, 'dashboard'])->middleware('auth:sanctum');
    Route::get('/user/devices', [RemoteController::class, 'getMyDevices']);
    Route::post('/tv/remote/ready', [RemoteController::class, 'tvReady']);
    Route::get('/channels/{id}/download', [DownloadController::class, 'downloadArchive']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/profile/socket-token', [ProfileController::class, 'getSocketToken']);
});
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/users/search', [AdminUserController::class, 'search']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::post('/users/adjust-balance', [AdminUserController::class, 'adjustBalance']);
    Route::post('/logos', [SettingController::class, 'updateLogos']);
    Route::post('/settings/tv-price', [SettingController::class, 'updateExtraTvPrice']);
    Route::prefix('channels')->group(function () {
    Route::post('/', [AdminChannelController::class, 'store']); 
    Route::put('/{id}', [AdminChannelController::class, 'update']); 
    Route::post('/sync', [AdminChannelController::class, 'sync']); 
     Route::prefix('{external_id}')->group(function () {
      Route::get('urls', [AdminChannelUrlController::class, 'index']);
      Route::post('urls', [AdminChannelUrlController::class, 'storeLive']);
      Route::delete('urls/{id}', [AdminChannelUrlController::class, 'destroyLive']);

      Route::post('archive-urls', [AdminChannelUrlController::class, 'storeArchive']);
      Route::delete('archive-urls/{id}', [AdminChannelUrlController::class, 'destroyArchive']);
    });
    });
    Route::get('/users', [AdminCategoryController::class, 'users']);
    Route::prefix('users/{userId}')->group(function () {
        Route::post('/grant-plan', [AdminPlansController::class, 'grantPlanToUser']);
        Route::post('/revoke-plan', [AdminPlansController::class, 'revokePlanFromUser']);
        Route::get('/plans',[AdminPlansController::class,'getUserPlans']);
    });
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
        Route::post('/{planId}/enable', [AdminPlansController::class, 'enablePlan']);
        Route::delete('/{planId}', [AdminPlansController::class, 'deletePlan']);
        Route::post('/{planId}/channels', [AdminPlansController::class, 'addChannelsToPlan']);
        Route::delete('/{planId}/channels', [AdminPlansController::class, 'removeChannelsFromPlan']);
        Route::get('/all', [AdminPlansController::class, 'allPlans']);
    });
     Route::prefix('discounts')->group(function () {
        Route::get('/', [AdminDiscountController::class, 'index']);          
        Route::post('/', [AdminDiscountController::class, 'store']);         
        Route::put('/{id}', [AdminDiscountController::class, 'update']);     
        Route::delete('/{id}', [AdminDiscountController::class, 'destroy']);  
        Route::post('/{id}/assign', [AdminDiscountController::class, 'assignToUser']); 
    });
    Route::apiResource('radios', AdminRadioController::class);
    Route::post('/notifications/global', [AdminNotificationController::class, 'broadcastGlobal']);
    Route::post('/notifications/user/{userId}', [AdminNotificationController::class, 'notifyUser']);
   
    Route::patch('/users/{userId}/role', [AdminUserController::class, 'updateRole']);

});
Route::post('/tv/init', [TvPairingController::class, 'initialize']);
Route::post('/tv/claim', [TvPairingController::class, 'claim'])
    ->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tv/pair', [TvPairingController::class, 'pair']);
});
Route::get('/settings/logos', [SettingController::class, 'getLogos']);
Route::get('/settings/tv-price', [SettingController::class, 'getTvPrice']);
Route::prefix('radio')->group(function () {
    Route::get('/', [RadioController::class, 'index']);
    Route::get('/{id}/stream', [RadioController::class, 'getStreamUrl']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/channels/{id}/heartbeat', [StatsController::class, 'heartbeat']);
});
Route::get('/metrics/realtime', [StatsController::class, 'getMetrics'])
    ->middleware('whitelist.ip');