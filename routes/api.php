<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AgencyAddonController;
use App\Http\Controllers\AgencyMarkupController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\TenantApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TenantAddonSettingController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantCustomerController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);
Route::get('/company/bootstrap', [CompanyController::class, 'bootstrap']);

/*
|--------------------------------------------------------------------------
| Flight / Duffel API Routes
|--------------------------------------------------------------------------
*/

Route::get('/places', [FlightController::class, 'searchPlaces']);

Route::post('/flights/search', [FlightController::class, 'searchFlights']);

Route::get('/offers', [FlightController::class, 'getOffers']);
Route::get('/offers/{offerId}', [FlightController::class, 'getOffer']);

Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{orderId}', [FlightController::class, 'getOrder']);
Route::patch('/orders/{orderId}', [FlightController::class, 'updateOrder']);
Route::get('/orders/{orderId}/available-services', [FlightController::class, 'getAvailableServices']);
Route::get('/seat-maps', [FlightController::class, 'getSeatMaps']);
Route::post('/payment-intents', [FlightController::class, 'createPaymentIntent']);
Route::post('/payment-intents/{paymentIntentId}/confirm', [FlightController::class, 'confirmPaymentIntent']);
Route::post('/orders', [FlightController::class, 'createOrder']);

Route::get('/order-refundable-status/{orderId}', [FlightController::class, 'checkOrderRefundable']);
Route::post('/order-cancellations', [FlightController::class, 'createOrderCancellation']);
Route::get('/order-cancellations/{cancellationId}', [FlightController::class, 'getOrderCancellation']);
Route::post('/order-cancellations/{cancellationId}/confirm/{orderId}', [FlightController::class, 'confirmOrderCancellation']);
Route::post('/orders/{orderId}/refunds/confirm', [FlightController::class, 'confirmOrderRefund']);

Route::get('/order-changeable-status/{orderId}', [FlightController::class, 'checkOrderChangeable']);
Route::post('/order-change-requests', [FlightController::class, 'createOrderChangeRequest']);
Route::get('/order-change-requests/{orderChangeRequestId}', [FlightController::class, 'getOrderChangeRequest']);
Route::post('/orders/{orderId}/change/approve', [FlightController::class, 'approveOrderChange']);
Route::post('/orders/{orderId}/change/reject', [FlightController::class, 'rejectOrderChange']);

Route::get('/order-change-offers/{orderChangeOfferId}', [FlightController::class, 'getOrderChangeOffer']);

Route::post('/order-changes', [FlightController::class, 'createOrderChange']);
Route::get('/order-changes/{orderChangeId}', [FlightController::class, 'getOrderChange']);
Route::post('/order-changes/{orderChangeId}/confirm', [FlightController::class, 'confirmOrderChange']);
Route::post('/orders/{orderId}/cancellation/approve', [FlightController::class, 'approveOrderCancellation']);
Route::post('/orders/{orderId}/cancellation/reject', [FlightController::class, 'rejectOrderCancellation']);

Route::get('/tenants/active', [TenantController::class, 'getActiveTenants']);
Route::post('/tenant-invitations/accept', [TenantMemberController::class, 'acceptInvitation']);

Route::get('/booking/available-addons', [AgencyAddonController::class, 'available']);
Route::get('/booking/markup', [AgencyMarkupController::class, 'booking']);
Route::get('/extras', [TenantAddonSettingController::class, 'getTenantAddonSettings']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/agency/addons', [AgencyAddonController::class, 'index'])
        ->middleware(['workspace.access', 'role:tenant_owner,system_developer,super_admin,admin']);
    Route::put('/agency/addons/{addon}', [AgencyAddonController::class, 'update'])
        ->middleware(['workspace.access', 'role:tenant_owner,system_developer,super_admin,admin']);
    Route::post('/agency/addons/{addon}/reset', [AgencyAddonController::class, 'reset'])
        ->middleware(['workspace.access', 'role:tenant_owner,system_developer,super_admin,admin']);
    Route::get('/agency/markup', [AgencyMarkupController::class, 'index']);
    Route::put('/agency/markup', [AgencyMarkupController::class, 'update']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('admin')->middleware('role:system_developer,super_admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/activities', [ActivityLogController::class, 'adminIndex']);
        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::get('/reports/export', [AdminReportController::class, 'export']);
        Route::get('/settings', [AdminSettingsController::class, 'platform']);
        Route::patch('/settings', [AdminSettingsController::class, 'updatePlatform']);
        Route::get('/tenants/pending', [TenantApprovalController::class, 'pending']);
        Route::post('/tenants/{tenant}/approve', [TenantApprovalController::class, 'approve']);
        Route::post('/tenants/{tenant}/reject', [TenantApprovalController::class, 'reject']);
        Route::post('/tenants/{tenant}/suspend', [TenantApprovalController::class, 'suspend']);
        Route::post('/tenants/{tenant}/reactivate', [TenantApprovalController::class, 'reactivate']);
    });

    Route::prefix('tenants')->middleware('workspace.access')->group(function () {
        Route::get('/dashboard', [TenantDashboardController::class, 'index']);
        Route::get('/activities', [ActivityLogController::class, 'tenantIndex']);
        Route::get('/customers', [TenantCustomerController::class, 'index']);
        Route::get('/settings', [AdminSettingsController::class, 'tenant']);
        Route::patch('/settings', [AdminSettingsController::class, 'updateTenant'])
            ->middleware('role:tenant_owner');
        Route::get('/members', [TenantMemberController::class, 'index'])
            ->middleware('role:tenant_owner,tenant_admin');
        Route::post('/members/invite', [TenantMemberController::class, 'invite'])
            ->middleware('role:tenant_owner,tenant_admin');
        Route::patch('/members/{member}/role', [TenantMemberController::class, 'changeRole'])
            ->middleware('role:tenant_owner,tenant_admin');
        Route::delete('/members/{member}', [TenantMemberController::class, 'remove'])
            ->middleware('role:tenant_owner,tenant_admin');
        Route::post('/invitations/{invitation}/resend', [TenantMemberController::class, 'resendInvite'])
            ->middleware('role:tenant_owner,tenant_admin');
    });
});

Route::prefix('bookings')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{order}', [OrderController::class, 'show']);
});
