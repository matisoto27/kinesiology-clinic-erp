<?php

namespace App\Observers;

use App\Models\Caja;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;

class PagoObserver
{
    /**
     * Handle the Pago "created" event.
     */
    public function created(Pago $pago): void
    {
        DB::transaction(function () use ($pago) {
            $caja = Caja::where('id', 1)->lockForUpdate()->first();
            if ($caja) {
                $caja->increment('saldo_actual', $pago->monto);
            }
        });
    }

    /**
     * Handle the Pago "deleted" event.
     */
    public function deleted(Pago $pago): void
    {
        DB::transaction(function () use ($pago) {
            $caja = Caja::where('id', 1)->lockForUpdate()->first();
            if ($caja) {
                $caja->decrement('saldo_actual', $pago->monto);
            }
        });
    }
}
