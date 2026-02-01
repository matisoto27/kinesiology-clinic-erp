<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sintomas_pacientes', function (Blueprint $table) {
            $table->id();

            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();

            $table->foreignId('id_sintoma')->constrained(table: 'sintomas');
            $table->foreignId('id_paciente')->constrained(table: 'pacientes');

            $table->unique(['id_sintoma', 'id_paciente', 'fecha_desde'], 'sint_pac_fecha_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sintomas_pacientes');
    }
};
