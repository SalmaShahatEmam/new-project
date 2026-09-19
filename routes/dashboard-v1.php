<?php


use App\Http\Controllers\Api\V1\Dashboard\{PermissionController, SupplierController,RoleController, AuthController, SettingController, UserController};
use App\Http\Controllers\Api\V1\Dashboard\CategoryController;
use App\Http\Controllers\Api\V1\Dashboard\LocationController;
use App\Http\Controllers\Api\V1\Dashboard\ProjectController;
use App\Http\Controllers\Api\V1\Dashboard\ResourceController;
use App\Http\Controllers\Api\V1\Dashboard\UnitController;
use Illuminate\Support\Facades\Route;
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

Route::prefix("auth")->group(function () {
    Route::middleware(["auth:api"])->group(function () {
        Route::post('resend-otp', [AuthController::class, 'resendOTP']);
        Route::post('verify-otp', [AuthController::class, 'verifyOTP']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('changpassword', [AuthController::class, 'changePassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::delete('delete-account', [AuthController::class, 'deleteAccount']);
    });
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('send-otp', [AuthController::class, 'sendOTP']);
            Route::post('register',[AuthController::class, 'register']);

});


Route::middleware(["auth:api"])->group(function () {
    # Roles
    Route::apiResource('roles', RoleController::class);

    #Locations 
    Route::apiResource('locations', LocationController::class);
    Route::get('locations/{location}/toggle-active' , [LocationController::class, 'toggleActive']);

    #Resource
    Route::apiResource('resources', ResourceController::class);
    Route::get('resources/{resource}/prices', [ResourceController::class, 'getPrices']);
    Route::get('resources/{resource}/toggle', [ResourceController::class, 'toggleActive']);

    Route::post('resources/{resource}/prices', [ResourceController::class, 'storePrices']);

    # Categories
    Route::apiResource('categories', CategoryController::class);
    # Permission
    Route::get('permissions', PermissionController::class);

 
    # Setting
    Route::apiResource('settings', SettingController::class);

    # Users
    Route::apiResource('users', UserController::class);

    # Admins
    Route::apiResource('admins', UserController::class);
    # Provider Requests 
    # Chat

    #Projects
    Route::apiResource('projects', ProjectController::class);
    Route::post("projects/{project}/resource", [ProjectController::class, 'addResource']);

    # Units
    Route::apiResource('units', UnitController::class);
    Route::get("units/{unit}/toggle-active", [UnitController::class, 'toggleActive']);

    # Suppliers
    Route::apiResource('suppliers', SupplierController::class);
    Route::get('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive']);
    include __DIR__ . '/chat.php';

    # Notification
    include __DIR__ . '/notification.php';
});
