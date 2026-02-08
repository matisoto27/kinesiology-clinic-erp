<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:generar-turnos-mensuales')->dailyAt('05:00');
