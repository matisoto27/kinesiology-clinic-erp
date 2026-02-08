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
        Schema::create('horarios_pacientes_fijos', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');

            $table->foreignId('id_paciente_fijo')
                ->constrained(table: 'pacientes_fijos')
                ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['id_paciente_fijo', 'dia_semana', 'hora_inicio'], 'pac_dia_hora_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios_pacientes_fijos');
    }
};
