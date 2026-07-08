<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::middleware(['web'])->prefix('auth')->group(function () {

    Route::post('/register/customer', [AuthController::class, 'registerCustomer']);

    Route::post('/register/agency', [AuthController::class, 'registerAgency']);

    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/email-booking-status', [AuthController::class, 'emailBookingStatus']);

    Route::post('/send-verification-code', [AuthController::class, 'sendEmailVerificationCode']);

    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    
    Route::post('/verify-email-code', [AuthController::class, 'verifyEmailWithCode']);

    Route::post('/resend-verification-code', [AuthController::class, 'resendVerificationEmail']);
});
