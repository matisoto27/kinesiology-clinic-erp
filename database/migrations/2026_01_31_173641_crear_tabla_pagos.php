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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('nro_pago');
            $table->enum('metodo', ['Efectivo', 'Transferencia']);
            $table->decimal('monto', total: 10, places: 2);
            $table->boolean('es_copago')->default(false);

            $table->foreignId('id_act_pac')->constrained(table: 'actividades_pacientes');
            $table->foreignId('id_profesional')->constrained(table: 'profesionales');

            $table->timestamps();

            $table->unique(['id_act_pac', 'nro_pago']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
