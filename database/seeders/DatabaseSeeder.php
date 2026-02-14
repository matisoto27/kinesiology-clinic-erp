<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CajaSeeder::class,
            TipoActividadSeeder::class,
            ActividadSeeder::class,
            ComboSeeder::class,
            HorarioSeeder::class,
            ObraSocialSeeder::class,
            PatologiaSeeder::class,
            ActividadComboSeeder::class,
            HorarioActividadSeeder::class
        ]);
    }
}
