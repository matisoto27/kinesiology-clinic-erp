<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:generar-turnos-mensuales')->dailyAt('05:00');
Schedule::command('app:aplicar-recargo-deuda-pacientes')->dailyAt('05:00');
