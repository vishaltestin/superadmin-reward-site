<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\LandingPageController;
use App\Http\Controllers\Api\Admin\SubAdminController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Storefront\PromotionController;
use App\Http\Controllers\Api\Storefront\StorefrontAuthController;
use App\Http\Controllers\Api\Website\DemoRequestController;
use App\Http\Controllers\Api\Website\LeadController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PaymentController;

Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'is.admin'])->group(function () {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        Route::prefix('profile')->group(function () {
            Route::get('/me', [ProfileController::class, 'me']);
            Route::put('/update', [ProfileController::class, 'update']);
            Route::post('/change-password', [ProfileController::class, 'changePassword']);
        });

        Route::get('/verticals', function (Illuminate\Http\Request $request) {
            $user = $request->user();

            if ($user->user_type === 'sub_admin') {
                $assignedVerticals = $user->managedVerticals()
                    ->where('is_active', true)
                    ->get(['verticals.id', 'verticals.name', 'verticals.slug']);

                return response()->json($assignedVerticals);
            }
            $companyVerticals = $user->company->verticals()
                ->where('verticals.is_active', true)
                ->get(['verticals.id', 'verticals.name', 'verticals.slug']);

            return response()->json($companyVerticals);
        });

        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::post('/', [EmployeeController::class, 'store']);
            Route::put('/{id}', [EmployeeController::class, 'update']);
            Route::post('/bulk-upload', [EmployeeController::class, 'bulkUpload']);
        });
        Route::prefix('email-templates')->group(function () {
            Route::get('/sidebar-events', [EmailTemplateController::class, 'getSidebarEvents']);

            Route::get('/', [EmailTemplateController::class, 'index']);

            Route::get('/{id}', [EmailTemplateController::class, 'show']);
            Route::put('/{id}', [EmailTemplateController::class, 'update']);
            Route::delete('/{id}', [EmailTemplateController::class, 'destroy']);

            Route::post('/{id}/duplicate', [EmailTemplateController::class, 'duplicateMaster']);
            Route::post('/upload-image', [EmailTemplateController::class, 'uploadImage']);
        });

        Route::prefix('landing-pages')->group(function () {
            Route::get('/sidebar-events', [LandingPageController::class, 'getSidebarEvents']);
            Route::get('/', [LandingPageController::class, 'index']);
            Route::get('/{id}', [LandingPageController::class, 'show']);
            Route::put('/{id}', [LandingPageController::class, 'update']);
            Route::delete('/{id}', [LandingPageController::class, 'destroy']);
            Route::post('/{id}/duplicate', [LandingPageController::class, 'duplicateMaster']);
            Route::post('/upload-image', [LandingPageController::class, 'uploadImage']);
        });

        Route::middleware(['role:business_head'])->group(function () {

            Route::prefix('sub-admins')->group(function () {
                Route::get('/', [SubAdminController::class, 'index']);
                Route::post('/', [SubAdminController::class, 'store']);
                Route::put('/{id}', [SubAdminController::class, 'update']);
            });

            Route::prefix('company')->group(function () {
                Route::put('/business-details', [CompanyController::class, 'updateBusinessDetails']);
                Route::post('/storefront-settings', [CompanyController::class, 'updateStorefrontSettings']);
                Route::get('/catalog-config', [CompanyController::class, 'getCatalogConfig']);
                Route::put('/catalog-visibility', [CompanyController::class, 'updateCatalogVisibility']);
            });

            Route::prefix('payment')->group(function () {
    Route::get('/balance', [PaymentController::class, 'balance']);
    Route::get('/transactions', [PaymentController::class, 'transactions']);
    Route::post('/top-up/mock', [PaymentController::class, 'mockTopUp']);
});
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
