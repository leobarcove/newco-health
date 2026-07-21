<?php

namespace App\Modules\Scheduling\Http;

use App\Http\Controllers\Controller;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Services\AvailabilityService;
use App\Modules\Scheduling\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
    ) {
    }

    /** Doctors a patient can book, with their next free slot as the hook. */
    public function doctors(): JsonResponse
    {
        $doctors = Doctor::where('status', Doctor::STATUS_ACTIVE)
            ->whereDate('licence_expires_at', '>', today())
            ->with('user')
            ->get()
            ->map(function (Doctor $doctor) {
                $next = null;
                $day = CarbonImmutable::now($doctor->timezone)->startOfDay();
                for ($i = 0; $i <= (int) config('booking.horizon_days') && $next === null; $i++) {
                    $next = $this->availability->slotsFor($doctor, $day->addDays($i))->first();
                }

                return [
                    'id' => $doctor->id,
                    'name' => $doctor->user->name,
                    'next_slot' => $next['starts_at']?->toIso8601String(),
                ];
            })
            ->filter(fn (array $d) => $d['next_slot'] !== null)
            ->sortBy('next_slot')
            ->values();

        return response()->json($doctors);
    }

    public function slots(Request $request, Doctor $doctor): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $slots = $this->availability->slotsFor(
            $doctor,
            CarbonImmutable::parse($data['date'], $doctor->timezone)->startOfDay(),
        );

        return response()->json($slots->map(fn (array $slot) => [
            'starts_at' => $slot['starts_at']->toIso8601String(),
            'ends_at' => $slot['ends_at']->toIso8601String(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'ulid', 'exists:doctors,id'],
            'starts_at' => ['required', 'date'],
            'complaint' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $patient = $request->user()->patient;
        abort_if($patient === null, 403, 'Only patients can book appointments.');

        $booking = $this->bookings->book(
            $patient,
            Doctor::findOrFail($data['doctor_id']),
            CarbonImmutable::parse($data['starts_at'])->utc(),
            $data['complaint'] ?? null,
        );

        return response()->json($this->serialise($booking), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if($patient === null, 403);

        $bookings = Booking::where('patient_id', $patient->id)
            ->orderByDesc('starts_at')
            ->limit(20)
            ->with('doctor.user')
            ->get();

        return response()->json($bookings->map(fn (Booking $b) => $this->serialise($b)));
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);

        $booking = $this->bookings->cancel($booking, 'patient', $request->user()->id);

        return response()->json($this->serialise($booking));
    }

    public function reschedule(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);

        $data = $request->validate(['starts_at' => ['required', 'date']]);

        $replacement = $this->bookings->reschedule(
            $booking,
            CarbonImmutable::parse($data['starts_at'])->utc(),
            $request->user()->id,
        );

        return response()->json($this->serialise($replacement), 201);
    }

    private function serialise(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'state' => $booking->state,
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'doctor' => ['id' => $booking->doctor_id, 'name' => $booking->doctor?->user?->name],
            'consult_id' => $booking->consult_id,
        ];
    }
}
