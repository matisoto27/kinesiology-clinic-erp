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
        Schema::create('obras_sociales_pacientes', function (Blueprint $table) {
            $table->id();

            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();

            $table->foreignId('id_obra_social')->constrained(table: 'obras_sociales');
            $table->foreignId('id_paciente')->constrained(table: 'pacientes');

            $table->unique(['id_obra_social', 'id_paciente', 'fecha_desde'], 'os_pac_fecha_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obras_sociales_pacientes');
    }
};
