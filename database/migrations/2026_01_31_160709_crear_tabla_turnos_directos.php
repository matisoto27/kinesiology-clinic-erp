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
        Schema::create('turnos_directos', function (Blueprint $table) {
            $table->id();

            $table->datetime('fecha_hora_registro')->index();
            $table->datetime('fecha_hora');
            $table->unsignedTinyInteger('nro_turno')->default(1); // Default(1): Gympass pide 1 solo turno ó Prueba de Pilates
            $table->string('estado')->default('Ausente'); // 'Ausente', 'Ausente avisó', 'Presente'

            // ID1: Gimnasio -> Gympass, ID2: Pilates -> Prueba pilates
            $table->foreignId('id_actividad')
                ->constrained(table: 'actividades')
                ->onDelete('cascade');

            $table->foreignId('id_paciente_casual')
                ->constrained(table: 'pacientes_casuales')
                ->onDelete('cascade');

            $table->foreignId('id_turno_original')
                ->nullable()
                ->constrained(table: 'turnos_directos')
                ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['id_paciente_casual', 'fecha_hora']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turnos_directos');
    }
};
