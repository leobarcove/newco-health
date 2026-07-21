<?php

namespace App\Modules\Scheduling\Console;

use App\Modules\Messaging\Services\SmsSender;
use App\Modules\Scheduling\Models\Booking;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'booking:send-reminders';

    protected $description = 'Send due appointment reminders (runs every five minutes) and sweep no-shows';

    public function handle(SmsSender $sms, \App\Modules\Scheduling\Services\BookingService $bookings): int
    {
        $sent = 0;

        foreach (config('booking.reminders') as $reminder) {
            $due = Booking::where('state', Booking::STATE_CONFIRMED)
                ->whereNull($reminder['column'])
                ->whereBetween('starts_at', [now(), now()->addMinutes($reminder['offset_minutes'])])
                ->with(['patient.user', 'doctor.user'])
                ->get();

            foreach ($due as $booking) {
                $phone = $booking->patient?->user?->phone;
                if ($phone !== null) {
                    $sms->send($phone, __('Reminder: your appointment with Dr :doctor is at :time. Open the app a few minutes early.', [
                        'doctor' => $booking->doctor->user->name,
                        'time' => $booking->starts_at->setTimezone($booking->doctor->timezone)->format('H:i, D j M'),
                    ]));
                }

                $booking->update([$reminder['column'] => now()]);
                $sent++;
            }
        }

        $noShows = $bookings->markNoShows();
        $this->info("Reminders sent: {$sent}; no-shows swept: {$noShows}");

        return self::SUCCESS;
    }
}
