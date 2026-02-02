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
        Schema::create('antecedentes_patologicos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_desde');

            $table->foreignId('id_paciente')->constrained(table: 'pacientes');
            $table->foreignId('id_patologia')->constrained(table: 'patologias');

            $table->unique(['id_paciente', 'id_patologia', 'fecha_desde'], 'pac_pat_fecha_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('antecedentes_patologicos');
    }
};
