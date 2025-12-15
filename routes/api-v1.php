<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\GeneralController;
use App\Http\Controllers\Api\V1\Client\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::controller(GeneralController::class)->group(function(){
    Route::get("cities/all","citiesIndex");

//Route::get('terms-and-conditions', 'getTermsAndConditions');

    #Static pages 
    Route::prefix("statis-pages")->group(function(){
            Route::get('terms-and-conditions', 'getTermsAndConditions');

    });
});


Route::prefix("auth")->group(function () {
    Route::middleware(["auth:api"])->group(function () {
        Route::post('resend-otp', [AuthController::class, 'resendOTP']); #
        Route::post('verify-otp', [AuthController::class, 'verifyOTP']); #
        Route::post('logout', [AuthController::class, 'logout']); #
        Route::get('profile', [AuthController::class, 'profile']); #
        Route::post('change-mobile', [AuthController::class, 'changeMobile']); #
        Route::post('chang-password', [AuthController::class, 'changePassword']); #
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::delete('delete-account', [AuthController::class, 'deleteAccount']);
    });
    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('send-otp', [AuthController::class, 'sendOTP']);

    Route::prefix("store")->group(function () {
           Route::post('register', [AuthController::class, 'providerRegister']); #
            Route::post('login', [AuthController::class, 'providerLogin']); #

    });

    Route::prefix("individual")->group(function(){
            Route::post('register', [AuthController::class, 'registerClient']); #
            Route::post('login', [AuthController::class, 'clientLogin']); #

    });
   
    
});


Route::middleware(["auth:api"])->group(function () {

    # Chat
    include __DIR__ . '/chat.php';
});
