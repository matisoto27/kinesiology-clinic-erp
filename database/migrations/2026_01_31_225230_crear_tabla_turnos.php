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
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('nro_turno');
            $table->datetime('fecha_hora');
            $table->boolean('asiste')->default(false);

            $table->foreignId('id_act_pac')->constrained(table: 'actividades_pacientes');

            $table->timestamps();

            $table->unique(['id_act_pac', 'fecha_hora']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
