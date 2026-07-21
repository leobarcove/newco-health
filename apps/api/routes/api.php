<?php

use App\Modules\Consults\Http\ConsultController;
use App\Modules\Consults\Http\DoctorConsultController;
use App\Modules\Consults\Http\MessageController;
use App\Modules\Identity\Http\AuthController;
use App\Modules\Prescribing\Http\PrescriptionController;
use App\Modules\Scheduling\Http\BookingController;
use App\Modules\Scheduling\Http\DoctorScheduleController;
use App\Modules\Payments\Http\PaymentController;
use App\Modules\Payments\Http\WebhookController;
use App\Modules\Payouts\Http\EarningsController;
use Illuminate\Support\Facades\Route;

// Provider webhooks — unauthenticated, HMAC-verified inside.
Route::post('webhooks/paystack', [WebhookController::class, 'paystack']);

Route::prefix('auth')->group(function () {
    Route::post('otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:10,1');
    Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
    Route::post('sponsor/register', [\App\Modules\Patients\Http\SponsorController::class, 'register'])->middleware('throttle:10,1');
    Route::post('sponsor/login', [\App\Modules\Patients\Http\SponsorController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware(['auth:sanctum', \App\Http\Middleware\SetUserLocale::class])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::patch('me', [AuthController::class, 'updateMe']);

    // Patient
    Route::get('consults', [ConsultController::class, 'index']);
    Route::post('consults', [ConsultController::class, 'store']);
    Route::get('consults/{consult}', [ConsultController::class, 'show'])
        ->middleware('phi.log:consult.read');
    Route::get('consults/{consult}/messages', [MessageController::class, 'index'])
        ->middleware('phi.log:consult.messages.read');
    Route::post('consults/{consult}/messages', [MessageController::class, 'store']);
    Route::post('consults/{consult}/attachments', [\App\Modules\Consults\Http\AttachmentController::class, 'store']);
    Route::get('consults/{consult}/messages/{message}/file', [\App\Modules\Consults\Http\AttachmentController::class, 'show'])
        ->middleware('phi.log:attachment.read');

    // Consents (NDPA ledger)
    Route::get('consents', [\App\Modules\Compliance\Http\ConsentController::class, 'index']);
    Route::post('consents', [\App\Modules\Compliance\Http\ConsentController::class, 'store']);

    // Dependants
    Route::get('dependants', [\App\Modules\Patients\Http\DependantController::class, 'index']);
    Route::post('dependants', [\App\Modules\Patients\Http\DependantController::class, 'store']);
    Route::delete('dependants/{dependant}', [\App\Modules\Patients\Http\DependantController::class, 'destroy']);

    // Sponsorships (patient side)
    Route::get('sponsorships', [\App\Modules\Patients\Http\SponsorshipController::class, 'index']);
    Route::post('sponsorships/{sponsorship}/respond', [\App\Modules\Patients\Http\SponsorshipController::class, 'respond']);

    // Sponsor portal
    Route::get('sponsor/overview', [\App\Modules\Patients\Http\SponsorController::class, 'overview']);
    Route::post('sponsor/beneficiaries', [\App\Modules\Patients\Http\SponsorController::class, 'invite']);
    Route::post('sponsor/wallet/topup', [\App\Modules\Patients\Http\SponsorController::class, 'topUp']);

    // Doctor
    Route::get('doctor/queue', [DoctorConsultController::class, 'queue']);
    Route::post('doctor/consults/{consult}/accept', [DoctorConsultController::class, 'accept']);
    Route::post('doctor/consults/{consult}/conclude', [DoctorConsultController::class, 'conclude']);

    // Prescribing
    Route::get('formulary', [PrescriptionController::class, 'formulary']);
    Route::post('doctor/consults/{consult}/prescriptions', [PrescriptionController::class, 'store']);
    Route::get('prescriptions/{prescription}', [PrescriptionController::class, 'show'])
        ->middleware('phi.log:prescription.read');
    Route::get('prescriptions/{prescription}/pdf', [PrescriptionController::class, 'pdf'])
        ->middleware('phi.log:prescription.pdf');

    // Payments
    Route::post('consults/{consult}/pay', [PaymentController::class, 'payForConsult']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::get('doctor/earnings', [EarningsController::class, 'index']);

    // Booking (patient)
    Route::get('booking/doctors', [BookingController::class, 'doctors']);
    Route::get('booking/doctors/{doctor}/slots', [BookingController::class, 'slots']);
    Route::get('bookings', [BookingController::class, 'index']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);
    Route::post('bookings/{booking}/pay', [BookingController::class, 'pay']);

    // Doctor clinical notes (SOAP-lite; doctor-only)
    Route::get('doctor/consults/{consult}/notes', [\App\Modules\Consults\Http\NoteController::class, 'show']);
    Route::put('doctor/consults/{consult}/notes', [\App\Modules\Consults\Http\NoteController::class, 'upsert']);

    // Booking (doctor)
    Route::get('doctor/availability', [DoctorScheduleController::class, 'availability']);
    Route::put('doctor/availability', [DoctorScheduleController::class, 'updateAvailability']);
    Route::get('doctor/agenda', [DoctorScheduleController::class, 'agenda']);
    Route::post('doctor/bookings/{booking}/begin', [DoctorScheduleController::class, 'begin']);
});
