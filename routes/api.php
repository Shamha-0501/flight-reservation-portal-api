<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);

//Flight Offers
Route::get('/flight-search', [FlightBookingController::class, 'searchFlightOffers']);
Route::post('/flight-offers', [FlightBookingController::class, 'selectFlightOffer']);
Route::post('/flight-offers/pricing', [FlightBookingController::class, 'selectFlightOfferPricing']);
Route::post('/booking/flight-orders', [FlightBookingController::class, 'flightCreateOrder']);