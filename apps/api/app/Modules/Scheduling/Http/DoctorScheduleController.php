<?php

namespace App\Modules\Scheduling\Http;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\AvailabilityTemplate;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorScheduleController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    public function availability(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        abort_if($doctor === null, 403);

        return response()->json(
            AvailabilityTemplate::where('doctor_id', $doctor->id)
                ->orderBy('weekday')->orderBy('start_time')
                ->get()
                ->map(fn (AvailabilityTemplate $t) => [
                    'id' => $t->id,
                    'weekday' => $t->weekday,
                    'start_time' => substr($t->start_time, 0, 5),
                    'end_time' => substr($t->end_time, 0, 5),
                    'slot_minutes' => $t->slot_minutes,
                    'active' => $t->active,
                ]),
        );
    }

    /** Replace the weekly template wholesale — the editor submits the full week. */
    public function updateAvailability(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        abort_if($doctor === null, 403);

        $data = $request->validate([
            'templates' => ['present', 'array', 'max:28'],
            'templates.*.weekday' => ['required', 'integer', 'between:1,7'],
            'templates.*.start_time' => ['required', 'date_format:H:i'],
            'templates.*.end_time' => ['required', 'date_format:H:i', 'after:templates.*.start_time'],
            'templates.*.slot_minutes' => ['required', 'integer', 'in:10,15,20,30,45,60'],
        ]);

        DB::transaction(function () use ($doctor, $data) {
            AvailabilityTemplate::where('doctor_id', $doctor->id)->delete();
            foreach ($data['templates'] as $template) {
                AvailabilityTemplate::create([...$template, 'doctor_id' => $doctor->id, 'active' => true]);
            }
        });

        return $this->availability($request);
    }

    /** The doctor's agenda for a given local date. */
    public function agenda(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        abort_if($doctor === null, 403);

        $data = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($data['date'] ?? 'today', $doctor->timezone);

        $bookings = Booking::where('doctor_id', $doctor->id)
            ->whereBetween('starts_at', [$date->startOfDay()->utc(), $date->endOfDay()->utc()])
            ->whereIn('state', [Booking::STATE_CONFIRMED, Booking::STATE_COMPLETED])
            ->orderBy('starts_at')
            ->with('patient.user')
            ->get();

        return response()->json($bookings->map(fn (Booking $b) => [
            'id' => $b->id,
            'starts_at' => $b->starts_at->toIso8601String(),
            'ends_at' => $b->ends_at->toIso8601String(),
            'state' => $b->state,
            'patient_name' => $b->patient?->user?->name ?: 'Patient',
            'consult_id' => $b->consult_id,
        ]));
    }

    public function begin(Request $request, Booking $booking): JsonResponse
    {
        $doctor = $request->user()->doctor;
        abort_if($doctor === null, 403);

        $consult = $this->bookings->begin($booking, $doctor);

        return response()->json(['consult_id' => $consult->id, 'state' => $consult->state], 201);
    }
}
