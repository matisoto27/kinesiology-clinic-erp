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
        Schema::create('actividades_pacientes', function (Blueprint $table) {
            $table->id();

            $table->date('fecha_comienzo');
            $table->unsignedTinyInteger('cant_sesiones');
            $table->boolean('es_fijo');
            $table->decimal('total_a_pagar', total: 10, places: 2);
            $table->date('fecha_emision_ord')->nullable();
            $table->boolean('pago_completado')->default(false);

            $table->foreignId('id_actividad')->constrained(table: 'actividades');
            $table->foreignId('id_paciente')->nullable()->constrained(table: 'pacientes');
            $table->foreignId('id_paciente_casual')->nullable()->constrained(table: 'pacientes_casuales');

            $table->timestamps();

            $table->unique(['id_actividad', 'id_paciente', 'fecha_comienzo'], 'act_pac_fecha_unique');
            $table->unique(['id_actividad', 'id_paciente_casual', 'fecha_comienzo'], 'act_casual_fecha_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividades_pacientes');
    }
};
