<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);

/*
|--------------------------------------------------------------------------
| Flight / Duffel API Routes
|--------------------------------------------------------------------------
*/

Route::get  ('/places', [FlightController::class, 'searchPlaces']);

Route::post ('/flights/search', [FlightController::class, 'searchFlights']);

Route::get  ('/offers', [FlightController::class, 'getOffers']);
Route::get  ('/offers/{offerId}', [FlightController::class, 'getOffer']);

Route::get  ('/orders', [FlightController::class, 'listOrders']);
Route::get  ('/orders/{orderId}', [FlightController::class, 'getOrder']);
Route::patch('/orders/{orderId}', [FlightController::class, 'updateOrder']);
Route::get  ('/orders/{orderId}/available-services', [FlightController::class, 'getAvailableServices']);
Route::get  ('/seat-maps', [FlightController::class, 'getSeatMaps']);
Route::post ('/payment-intents', [FlightController::class, 'createPaymentIntent']);
Route::post ('/payment-intents/{paymentIntentId}/confirm', [FlightController::class, 'confirmPaymentIntent']);
Route::post ('/orders', [FlightController::class, 'createOrder']);

Route::post ('/order-cancellations', [FlightController::class, 'createOrderCancellation']);
Route::get  ('/order-cancellations/{cancellationId}', [FlightController::class, 'getOrderCancellation']);
Route::post ('/order-cancellations/{cancellationId}/confirm', [FlightController::class, 'confirmOrderCancellation']);

Route::post ('/order-change-requests', [FlightController::class, 'createOrderChangeRequest']);
Route::get  ('/order-change-requests/{orderChangeRequestId}', [FlightController::class, 'getOrderChangeRequest']);

Route::get  ('/order-change-offers/{orderChangeOfferId}', [FlightController::class, 'getOrderChangeOffer']);

Route::post ('/order-changes', [FlightController::class, 'createOrderChange']);
Route::get  ('/order-changes/{orderChangeId}', [FlightController::class, 'getOrderChange']);
Route::post ('/order-changes/{orderChangeId}/confirm', [FlightController::class, 'confirmOrderChange']);