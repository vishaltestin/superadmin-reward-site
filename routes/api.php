<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\LeadController;

// ----------------------------------------------------
// PUBLIC ROUTES
// ----------------------------------------------------
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/leads', [LeadController::class, 'store']);

// ----------------------------------------------------
// PROTECTED ROUTES (Sanctum SPA Authentication)
// ----------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::get('/user/me', [ProfileController::class, 'me']);
    Route::put('/user/profile', [ProfileController::class, 'update']);
    
    Route::post('/company/business-profile', [CompanyController::class, 'updateBusiness']);
});