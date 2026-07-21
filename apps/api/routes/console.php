<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('booking:send-reminders')->everyFiveMinutes();
