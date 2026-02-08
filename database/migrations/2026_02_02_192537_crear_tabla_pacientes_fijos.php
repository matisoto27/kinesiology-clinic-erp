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
        Schema::create('pacientes_fijos', function (Blueprint $table) {
            $table->id();
            $table->boolean('activo')->default(true);

            $table->foreignId('id_actividad')->constrained(table: 'actividades');
            $table->foreignId('id_paciente')->constrained(table: 'pacientes');

            $table->timestamps();

            $table->unique(['id_actividad', 'id_paciente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pacientes_fijos');
    }
};
