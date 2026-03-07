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
            $table->string('estado', length: 20)->default('Ausente'); // Ausente - Ausente avisó - Presente

            $table->foreignId('id_act_pac')
                ->constrained(table: 'actividades_pacientes')
                ->onDelete('cascade');

            $table->foreignId('id_turno_original')
                ->nullable()
                ->constrained(table: 'turnos')
                ->onDelete('cascade');

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
