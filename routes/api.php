<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TenantAddonSettingController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);

/*
|--------------------------------------------------------------------------
| Flight / Duffel API Routes
|--------------------------------------------------------------------------
*/

Route::get('/places', [FlightController::class, 'searchPlaces']);

Route::post('/flights/search', [FlightController::class, 'searchFlights']);

Route::get('/offers', [FlightController::class, 'getOffers']);
Route::get('/offers/{offerId}', [FlightController::class, 'getOffer']);

Route::get('/orders', [FlightController::class, 'listOrders']);
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

Route::get('/order-changeable-status/{orderId}', [FlightController::class, 'checkOrderChangeable']);
Route::post('/order-change-requests', [FlightController::class, 'createOrderChangeRequest']);
Route::get('/order-change-requests/{orderChangeRequestId}', [FlightController::class, 'getOrderChangeRequest']);

Route::get('/order-change-offers/{orderChangeOfferId}', [FlightController::class, 'getOrderChangeOffer']);

Route::post('/order-changes', [FlightController::class, 'createOrderChange']);
Route::get('/order-changes/{orderChangeId}', [FlightController::class, 'getOrderChange']);
Route::post('/order-changes/{orderChangeId}/confirm', [FlightController::class, 'confirmOrderChange']);

Route::get('/tenants/active', [TenantController::class, 'getActiveTenants']);
Route::get('/extras', [TenantAddonSettingController::class, 'getTenantAddonSettings']);

Route::prefix('bookings')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{order}', [OrderController::class, 'show']);
});

