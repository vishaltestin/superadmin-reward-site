<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Website\LeadController;
use App\Http\Controllers\Api\Website\DemoRequestController;

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\CompanyController;

use App\Http\Controllers\Api\Storefront\StorefrontAuthController;
use App\Http\Controllers\Api\Storefront\PromotionController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\SubAdminController;

use App\Http\Controllers\Api\ProfileController;
use App\Models\Vertical;




Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'is.admin'])->group(function () {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        Route::get('/profile/me', [ProfileController::class, 'me']);
        Route::put('/profile/update', [ProfileController::class, 'update']);
        Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

        Route::get('/verticals', function () {
            return response()->json(\App\Models\Vertical::where('is_active', true)->get());
        });

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::post('/employees/bulk-upload', [EmployeeController::class, 'bulkUpload']);

        Route::middleware(['role:business_head'])->group(function () {
            Route::get('/sub-admins', [SubAdminController::class, 'index']);
            Route::post('/sub-admins', [SubAdminController::class, 'store']);
            Route::put('/sub-admins/{id}', [SubAdminController::class, 'update']);
            Route::put('/company/business-details', [CompanyController::class, 'updateBusinessDetails']);
            Route::post('/company/storefront-settings', [CompanyController::class, 'updateStorefrontSettings']);
            Route::put('/company/catalog-visibility', [CompanyController::class, 'updateCatalogVisibility']);
        });
    });
});





















Route::prefix('website')->group(function () {
    Route::post('/leads', [LeadController::class, 'store']);
    Route::post('/demo-requests', [DemoRequestController::class, 'store']);
});


Route::prefix('storefront')->group(function () {
    Route::post('/auth/login', [StorefrontAuthController::class, 'login']);
    Route::get('/promotions', [PromotionController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [StorefrontAuthController::class, 'logout']);
        Route::get('/user/me', [ProfileController::class, 'me']);
        Route::put('/user/profile', [ProfileController::class, 'update']);
        Route::post('/user/change-password', [ProfileController::class, 'changePassword']);
    });
});