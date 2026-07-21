<?php

namespace App\Modules\Consults\Services;

use App\Models\User;
use App\Modules\Consults\Models\Consult;
use Illuminate\Support\Facades\Http;

/**
 * Daily.co driver (activates on DAILY_API_KEY). Rooms are private,
 * named by consult ULID, and expire with the follow-up window.
 */
class DailyVideoGateway implements VideoGateway
{
    public function name(): string
    {
        return 'daily';
    }

    public function ensureRoom(Consult $consult): string
    {
        $name = 'consult-'.strtolower($consult->id);

        $existing = Http::withToken(config('services.daily.key'))
            ->get("https://api.daily.co/v1/rooms/{$name}");

        if ($existing->successful()) {
            return $existing->json('url');
        }

        return Http::withToken(config('services.daily.key'))
            ->post('https://api.daily.co/v1/rooms', [
                'name' => $name,
                'privacy' => 'private',
                'properties' => [
                    'exp' => now()->addHours(4)->timestamp,
                    'enable_screenshare' => false,
                    'start_video_off' => true, // voice-first — video is the upgrade (ladder)
                ],
            ])->throw()->json('url');
    }

    public function participantToken(Consult $consult, User $user, bool $isOwner): string
    {
        return Http::withToken(config('services.daily.key'))
            ->post('https://api.daily.co/v1/meeting-tokens', [
                'properties' => [
                    'room_name' => 'consult-'.strtolower($consult->id),
                    'user_name' => $isOwner ? 'Dr '.$user->name : ($user->name ?: 'Patient'),
                    'is_owner' => $isOwner,
                    'exp' => now()->addHours(2)->timestamp,
                ],
            ])->throw()->json('token');
    }
}
