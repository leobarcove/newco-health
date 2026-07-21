<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('booking:send-reminders')->everyFiveMinutes();
Schedule::command('consults:close-followups')->hourly();
Schedule::command('programmes:tick')->hourly();
Schedule::command('payouts:run')->weeklyOn(5, '09:00'); // Fridays, Lagos morning
Schedule::command('payments:reconcile')->dailyAt('02:00');
Schedule::command('compliance:prune')->dailyAt('03:30');
