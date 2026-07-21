<?php

use App\Modules\Consults\Http\ConsultController;
use App\Modules\Consults\Http\DoctorConsultController;
use App\Modules\Consults\Http\MessageController;
use App\Modules\Identity\Http\AuthController;
use App\Modules\Prescribing\Http\PrescriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:10,1');
    Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);

    // Patient
    Route::get('consults', [ConsultController::class, 'index']);
    Route::post('consults', [ConsultController::class, 'store']);
    Route::get('consults/{consult}', [ConsultController::class, 'show']);
    Route::get('consults/{consult}/messages', [MessageController::class, 'index']);
    Route::post('consults/{consult}/messages', [MessageController::class, 'store']);

    // Doctor
    Route::get('doctor/queue', [DoctorConsultController::class, 'queue']);
    Route::post('doctor/consults/{consult}/accept', [DoctorConsultController::class, 'accept']);
    Route::post('doctor/consults/{consult}/conclude', [DoctorConsultController::class, 'conclude']);

    // Prescribing
    Route::get('formulary', [PrescriptionController::class, 'formulary']);
    Route::post('doctor/consults/{consult}/prescriptions', [PrescriptionController::class, 'store']);
    Route::get('prescriptions/{prescription}', [PrescriptionController::class, 'show']);
});
