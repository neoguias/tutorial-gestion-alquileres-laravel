<?php

use Illuminate\Support\Facades\Schedule;

// El día 1 de cada mes se emiten los recibos, y cada mañana se avisa de los impagados.
Schedule::command('receipts:issue')->monthlyOn(1, '08:00');
Schedule::command('receipts:remind')->dailyAt('09:00');
