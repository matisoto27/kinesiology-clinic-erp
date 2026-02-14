<?php

namespace App\Observers;

use App\Models\Caja;
use App\Models\Egreso;
use Illuminate\Support\Facades\DB;

class EgresoObserver
{
    /**
     * Handle the Egreso "created" event.
     */
    public function created(Egreso $egreso): void
    {
        DB::transaction(function () use ($egreso) {
            $caja = Caja::where('id', 1)->lockForUpdate()->first();
            if ($caja) {
                $caja->decrement('saldo_actual', $egreso->monto);
            }
        });
    }

    /**
     * Handle the Egreso "deleted" event.
     */
    public function deleted(Egreso $egreso): void
    {
        DB::transaction(function () use ($egreso) {
            $caja = Caja::where('id', 1)->lockForUpdate()->first();
            if ($caja) {
                $caja->increment('saldo_actual', $egreso->monto);
            }
        });
    }
}
