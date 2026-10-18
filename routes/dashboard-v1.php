<?php

use App\Http\Controllers\Api\V1\Dashboard\ActivityHistoryController;
use App\Http\Controllers\Api\V1\Dashboard\AssistantController;
use App\Http\Controllers\Api\V1\Dashboard\AuthController;
use App\Http\Controllers\Api\V1\Dashboard\CategoryController;
use App\Http\Controllers\Api\V1\Dashboard\LocationController;
use App\Http\Controllers\Api\V1\Dashboard\PermissionController;
use App\Http\Controllers\Api\V1\Dashboard\ProjectController;
use App\Http\Controllers\Api\V1\Dashboard\ResourceController;
use App\Http\Controllers\Api\V1\Dashboard\RoleController;
use App\Http\Controllers\Api\V1\Dashboard\SiteController;
use App\Http\Controllers\Api\V1\Dashboard\SettingController;
use App\Http\Controllers\Api\V1\Dashboard\SupplierController;
use App\Http\Controllers\Api\V1\Dashboard\UnitController;
use App\Http\Controllers\Api\V1\Dashboard\UserController;
use App\Http\Controllers\Api\V1\GeneralController;
use App\Models\Project;
use App\Models\User;
use App\Notifications\UserNotification;
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

Route::prefix('auth')->group(function () {
    Route::middleware(['auth:api'])->group(function () {
        Route::post('resend-otp', [AuthController::class, 'resendOTP']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('changpassword', [AuthController::class, 'changePassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::delete('delete-account', [AuthController::class, 'deleteAccount']);
    });
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('send-otp', [AuthController::class, 'sendOTP']);
    Route::post('register', [AuthController::class, 'register']);

    Route::post('verify-otp', [AuthController::class, 'verifyOTP']);

});

Route::middleware(['auth:api'])->group(function () {

    Route::post('admin/assistant/ask', [AssistantController::class, 'ask']);

    Route::get('history', [ActivityHistoryController::class, 'index']);

    // Roles
    Route::apiResource('roles', RoleController::class);

    // Locations
    Route::get('active-locations', [LocationController::class, 'active']);
    Route::apiResource('locations', LocationController::class);
    Route::get('locations/{location}/toggle-active', [LocationController::class, 'toggleActive']);

    // Sites
    Route::post('reports-and-problems/by-date', [SiteController::class, 'reportsAndProblemsByDate']);
    Route::post('sites/{site}/problems', [SiteController::class, 'addProblemToSite']);
    Route::get('sites/{site}/problems', [SiteController::class, 'siteProblems']);
    Route::patch('site-problems/{problem}/status', [SiteController::class, 'updateSiteProblemStatus']);
    Route::apiResource('sites', SiteController::class);
    Route::post('addUserToSite', [SiteController::class, 'addUserToSite']);
    Route::delete('removeUserFromSite', [SiteController::class, 'removeUserFromSite']);
    Route::patch('sites/{site}/status', [SiteController::class, 'updateStatus'])->middleware('role:admin');
    Route::patch('sites/{site}/durationExpand', [SiteController::class, 'durationExpand'])->middleware('role:admin');
    // add report to site

    Route::post('addReportToSite', [SiteController::class, 'addReportToSite']);

    // Resource
    Route::apiResource('resources', ResourceController::class);
    Route::get('resources/{resource}/prices', [ResourceController::class, 'getPrices']);
    Route::get('resources/{resource}/toggle', [ResourceController::class, 'toggleActive']);
    Route::get('my-prices', [ResourceController::class, 'myPrices']);

    Route::post('resources/{resource}/prices', [ResourceController::class, 'storePrices']);

    // Categories
    Route::apiResource('categories', CategoryController::class);
    // Permission
    Route::get('permissions', PermissionController::class);

    // Setting
    Route::apiResource('settings', SettingController::class);

    // Users
    Route::apiResource('users', UserController::class);

    Route::get("getUser/{role}", [UserController::class, 'getUsersByRole']);

    // Admins
    Route::apiResource('admins', UserController::class);

    
    Route::get('admins/{admin}/status/{status}', [UserController::class, 'changeStatus']);
    // Provider Requests
    // Chat

    // Projects
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/copy', [ProjectController::class, 'copy']);
    Route::post('projects/{project}/resource', [ProjectController::class, 'addResource']);
    Route::delete('projects/{project}/resources/{resourceId}', [ProjectController::class, 'destroyResource']);
    Route::get('all-projects', [ProjectController::class, 'all']);
    Route::post('projects/{project}/assign', [ProjectController::class, 'addAssignee']);
    Route::post('projects/{project}/status', [ProjectController::class, 'updateStatus']);
    Route::post('projects/{project}/reopen', [ProjectController::class, 'reopen']);
    Route::post('projects/{item}/add-note', [ProjectController::class, 'addNote']);
    // Units
    Route::get('active-units', [UnitController::class, 'active']);
    Route::apiResource('units', UnitController::class);

    Route::get('units/{unit}/toggle-active', [UnitController::class, 'toggleActive']);

    Route::get('home', [GeneralController::class, 'home']);

    // Suppliers
    Route::get('active-suppliers', [SupplierController::class, 'active']);
    Route::apiResource('suppliers', SupplierController::class);
    Route::get('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive']);
    Route::get('send-notification', function () {
        $user = User::find(1);
        $project = Project::find(50);
        $data = [
            'title' => [
                'en' => ' Request ',
                'ar' => 'طلب   ',
            ],
            'body' => [
                'en' => 'gg',
                'ar' => 'ق',
            ],
            'id' => $project->id,
            'type' => class_basename($project),
            'serial' => null,
        ];

        $user->notify(new UserNotification($data));

    });


    include __DIR__.'/chat.php';

    // Notification
    include __DIR__.'/notification.php';
});
