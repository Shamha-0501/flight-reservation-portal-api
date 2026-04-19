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

// Location / Airport search (DEL, SYD, Colombo, etc.)
Route::get('/places', [FlightController::class, 'searchPlaces']);
// Flight search (creates offer request + returns offers)
Route::post('/flights/search', [FlightController::class, 'searchFlights']);
// Get offers by offer_request_id
Route::get('/offers', [FlightController::class, 'getOffers']);
// Get single offer details
Route::get('/offers/{offerId}', [FlightController::class, 'getOffer']);
// Create booking (order)
Route::post('/orders', [FlightController::class, 'createOrder']);
// Get order details
Route::get('/orders/{orderId}', [FlightController::class, 'getOrder']);