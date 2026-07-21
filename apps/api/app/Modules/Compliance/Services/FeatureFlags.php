<?php

namespace App\Modules\Compliance\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Cached DB-driven flags — patient-visible changes ship dark, flip live. */
class FeatureFlags
{
    private const CACHE_KEY = 'features.all';

    /** Unknown keys are OFF — features ship dark by default. */
    public function enabled(string $key): bool
    {
        return $this->all()[$key] ?? false;
    }

    /** @return array<string, bool> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            return DB::table('features')->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all();
        });
    }

    public function set(string $key, bool $enabled, ?string $note = null): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => $key],
            ['enabled' => $enabled, 'note' => $note, 'updated_at' => now(), 'created_at' => now()],
        );
        Cache::forget(self::CACHE_KEY);
    }
}
