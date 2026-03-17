<?php

namespace Database\Seeders;

use App\Models\Caja;
use Illuminate\Database\Seeder;

class CajaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Caja::firstOrCreate(
            ['id' => 1],
            ['saldo_efectivo' => 0],
            ['saldo_transferencia' => 0]
        );
    }
}
