<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\CampaignController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\EventController;
use App\Http\Controllers\Api\Admin\LandingPageController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\SubAdminController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Storefront\ClaimController;
use App\Http\Controllers\Api\Storefront\PromotionController;
use App\Http\Controllers\Api\Storefront\StorefrontAuthController;
use App\Http\Controllers\Api\Storefront\StorefrontCatalogController;
use App\Http\Controllers\Api\Storefront\StorefrontCheckoutController;
use App\Http\Controllers\Api\Storefront\StorefrontConfigController;
use App\Http\Controllers\Api\Storefront\StorefrontUserController;
use App\Http\Controllers\Api\Website\DemoRequestController;
use App\Http\Controllers\Api\Website\LeadController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login']);
//     Route::get('/test-wipe', function () {
//     $companyId = 4; // Change this to your target company ID

//     // 🚨 Disable foreign key checks to prevent MySQL from blocking our wipe
//     \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');

//     \Illuminate\Support\Facades\DB::transaction(function () use ($companyId) {

//         // 1. Get Campaign IDs (including soft-deleted ones)
//         $campaignIds = \App\Models\Campaign::withTrashed()->where('company_id', $companyId)->pluck('id');

//         // Use DB::table to FORCE physical deletions
//         \Illuminate\Support\Facades\DB::table('campaign_entitlements')->whereIn('campaign_id', $campaignIds)->delete();
//         \Illuminate\Support\Facades\DB::table('campaigns')->where('company_id', $companyId)->delete();

//         // 2. Get Order IDs (including soft-deleted ones)
//         $orderIds = \App\Models\Order::withTrashed()->where('company_id', $companyId)->pluck('id');
//         \Illuminate\Support\Facades\DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
//         \Illuminate\Support\Facades\DB::table('orders')->where('company_id', $companyId)->delete();

//         // 3. Gather Tenant User Collections
//         $allUserIds = \App\Models\User::where('company_id', $companyId)->pluck('id');

//         // 4. Clean out the Ledger Transactions and Wallets entirely
//         $walletIds = \Illuminate\Support\Facades\DB::table('wallets')
//             ->where(function($q) use ($companyId) {
//                 $q->where('walletable_type', 'App\Models\Company')->where('walletable_id', $companyId);
//             })
//             ->orWhere(function($q) use ($allUserIds) {
//                 $q->where('walletable_type', 'App\Models\User')->whereIn('walletable_id', $allUserIds);
//             })
//             ->pluck('id');

//         \Illuminate\Support\Facades\DB::table('transactions')->whereIn('wallet_id', $walletIds)->delete();
//         \Illuminate\Support\Facades\DB::table('wallets')->whereIn('id', $walletIds)->delete();

//         // 5. Wipe Metadata Profiles & Delivery Addresses
//         \Illuminate\Support\Facades\DB::table('rewardee_profiles')->where('company_id', $companyId)->delete();
//         \Illuminate\Support\Facades\DB::table('user_addresses')->whereIn('user_id', $allUserIds)->delete();

//         // 6. Delete all Rewardees/Employees
//         \Illuminate\Support\Facades\DB::table('users')->where('company_id', $companyId)->where('user_type', 'rewardee')->delete();

//         // 7. Re-initialize empty Wallets for your Company Admins
//         $admins = \App\Models\User::where('company_id', $companyId)->whereIn('user_type', ['business_head', 'sub_admin'])->get();
//         foreach ($admins as $admin) {
//             $admin->wallet()->create(['balance' => 0.00]);
//         }

//         // 8. Re-initialize empty Wallet for the Company
//         $company = \App\Models\Company::find($companyId);
//         if ($company) {
//             $company->wallet()->create(['balance' => 0.00]);
//         }
//     });

//     // 🚨 Turn foreign key checks back on!
//     \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

//     return "Company {$companyId} transaction architecture successfully reset to zero! Foreign key checks restored.";
// });

    Route::middleware(['auth:sanctum', 'is.admin'])->group(function () {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        Route::prefix('profile')->group(function () {
            Route::get('/me', [ProfileController::class, 'me']);
            Route::put('/update', [ProfileController::class, 'update']);
            Route::post('/change-password', [ProfileController::class, 'changePassword']);
        });

        Route::get('/events', [EventController::class, 'index']);

        Route::prefix('campaigns')->group(function () {
            Route::get('/', [CampaignController::class, 'index']);

            Route::get('/{id}', [CampaignController::class, 'show']);

            Route::post('/', [CampaignController::class, 'store']);

            Route::post('/{id}/cancel', [CampaignController::class, 'cancel']);

            Route::get('/{id}/export', [CampaignController::class, 'exportReport']);
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
            Route::delete('/{id}', [EmployeeController::class, 'destroy']);
            Route::post('/bulk-upload', [EmployeeController::class, 'bulkUpload']);
            Route::post('/{id}/promote', [EmployeeController::class, 'promoteToAdmin']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/calendar-events', [DashboardController::class, 'getCalendarEvents']);
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
        Route::prefix('reports')->group(function () {
            Route::get('/overview', [ReportController::class, 'getOverview']);
            Route::get('/trends', [ReportController::class, 'getTrends']);
            Route::get('/distribution', [ReportController::class, 'getDistribution']);
            Route::get('/top-recipients', [ReportController::class, 'getTopRecipients']);
            Route::get('/campaigns', [ReportController::class, 'getCampaigns']);
            Route::get('/products', [ReportController::class, 'getProductReports']);
            Route::get('/recent-activity', [ReportController::class, 'getRecentActivity']);
        });

        Route::middleware(['role:business_head'])->group(function () {

            Route::prefix('sub-admins')->group(function () {
                Route::get('/', [SubAdminController::class, 'index']);
                Route::post('/', [SubAdminController::class, 'store']);
                Route::put('/{id}', [SubAdminController::class, 'update']);
                Route::delete('/{id}', [SubAdminController::class, 'destroy']);
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
                Route::post('/razorpay/order', [PaymentController::class, 'createOrder']);
                Route::post('/razorpay/verify', [PaymentController::class, 'verifyPayment']);
            });
        });
    });
});

Route::prefix('website')->group(function () {
    Route::post('/leads', [LeadController::class, 'store']);
    Route::post('/demo-requests', [DemoRequestController::class, 'store']);
});

Route::prefix('storefront')->group(function () {
    Route::get('/promotions', [PromotionController::class, 'index']);

    Route::prefix('{slug}')->group(function () {
        Route::get('/init', [StorefrontConfigController::class, 'initializeStore']);
        Route::post('/auth/login', [StorefrontAuthController::class, 'login']);
        Route::get('/search', [StorefrontCatalogController::class, 'search']);

        Route::get('/categories', [StorefrontCatalogController::class, 'categories']);
        Route::get('/products', [StorefrontCatalogController::class, 'products']);
        Route::get('/products/{productSlug}', [StorefrontCatalogController::class, 'productDetail']);

        Route::middleware(['auth:sanctum', 'storefront.tenant'])->group(function () {
            Route::post('/auth/logout', [StorefrontAuthController::class, 'logout']);
            Route::get('/user/me', [ProfileController::class, 'me']);
            Route::put('/user/profile', [ProfileController::class, 'update']);
            Route::post('/user/change-password', [ProfileController::class, 'changePassword']);
            Route::post('/checkout', [StorefrontCheckoutController::class, 'checkout']);
            Route::post('/checkout/verify', [StorefrontCheckoutController::class, 'verifyPayment']);
            Route::prefix('user')->group(function () {
                Route::get('/wallet', [StorefrontUserController::class, 'wallet']);
                Route::get('/vouchers', [StorefrontUserController::class, 'vouchers']);
                Route::get('/orders', [StorefrontUserController::class, 'orders']);
                Route::get('/orders/{orderNumber}', [StorefrontUserController::class, 'showOrder']);
                Route::prefix('claim')->group(function () {
                    Route::get('/catalog', [ClaimController::class, 'catalog']);
                    Route::get('/validate', [ClaimController::class, 'validateToken']);
                    Route::post('/execute', [ClaimController::class, 'executeClaim']);
                    Route::post('/validate-code', [ClaimController::class, 'validateCode']);
                });
            });
        });
    });
});
