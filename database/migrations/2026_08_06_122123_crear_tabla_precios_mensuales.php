<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('precios_mensuales', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('frecuencia_semanal');
            $table->date('fecha_desde');
            $table->decimal('valor', total: 10, places: 2);

            $table->timestamps();

            $table->unique(['frecuencia_semanal', 'fecha_desde']);
        });

        DB::statement('ALTER TABLE precios_mensuales ADD CONSTRAINT chk_frecuencia_semanal CHECK (frecuencia_semanal BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE precios_mensuales ADD CONSTRAINT chk_valor_positivo CHECK (valor > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('precios_mensuales');
    }
};
