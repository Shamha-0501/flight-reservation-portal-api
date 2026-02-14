<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);

//Flight Offers
Route::get('/flightSearch', [FlightSearchController::class, 'searchFlightOffers']);