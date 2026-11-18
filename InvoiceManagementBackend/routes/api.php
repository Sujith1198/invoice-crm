<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// PDF invoice stream is accessible directly or authenticated
Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);

/*
|--------------------------------------------------------------------------
| Application Core API (Supports both Sanctum & Direct session/bearer)
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => []], function () {

    // Auth verification
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Customers
    Route::get('/customers/search', [CustomerController::class, 'search']);
    Route::get('/customers/cities', [CustomerController::class, 'cities']);
    Route::post('/customers/check-duplicate', [CustomerController::class, 'checkDuplicate']);
    Route::apiResource('customers', CustomerController::class);

    // Items
    Route::get('/items/search', [ItemController::class, 'search']);
    Route::apiResource('items', ItemController::class);

    // Invoices
    Route::get('/invoices/next-number', [InvoiceController::class, 'nextNumber']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
    Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);
    Route::apiResource('invoices', InvoiceController::class);

    // Notifications (Pending payments: Daily, 15 Days, Monthly)
    Route::get('/notifications/pending-payments', [NotificationController::class, 'pendingPayments']);
    Route::post('/notifications/approve-payment/{invoice}', [NotificationController::class, 'approvePayment']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/daily', [ReportController::class, 'daily']);
        Route::get('/weekly', [ReportController::class, 'weekly']);
        Route::get('/monthly', [ReportController::class, 'monthly']);
        Route::get('/yearly', [ReportController::class, 'yearly']);
        Route::get('/revenue', [ReportController::class, 'revenue']);
        Route::get('/customers', [ReportController::class, 'customers']);
        Route::get('/items', [ReportController::class, 'items']);
        Route::get('/outstanding', [ReportController::class, 'outstanding']);
        Route::get('/paid', [ReportController::class, 'paid']);
        Route::get('/unpaid', [ReportController::class, 'unpaid']);
        Route::get('/gst', [ReportController::class, 'gst']);
    });

    // Users & Roles
    Route::apiResource('users', UserController::class);

    // Settings
    Route::get('/settings', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);
});
