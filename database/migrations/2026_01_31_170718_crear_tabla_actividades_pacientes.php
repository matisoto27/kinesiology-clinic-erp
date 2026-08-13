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

            $table->unsignedTinyInteger('cant_sesiones');
            $table->decimal('total_a_pagar', total: 10, places: 2);
            $table->boolean('pago_completado')->default(false);
            $table->date('fecha_emision_ord')->nullable();
            $table->date('fecha_recargo')->nullable()->index();
            $table->decimal('porcentaje_recargo', total: 5, places: 2)->nullable();
            $table->decimal('monto_recargo', total: 10, places: 2)->nullable();

            $table->foreignId('id_actividad')->constrained(table: 'actividades');
            $table->foreignId('id_paciente')->nullable()->constrained(table: 'pacientes');
            $table->foreignId('id_paciente_casual')->nullable()->constrained(table: 'pacientes_casuales');
            $table->unsignedTinyInteger('frecuencia_total_dual')->nullable();
            $table->foreignId('id_act_pac_dual')
                ->nullable()
                ->constrained(table: 'actividades_pacientes');

            $table->timestamps();
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
