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
        Schema::create('horas_trabajadas', function (Blueprint $table) {
            $table->id();

            $table->decimal('valor_hora_profesional', total: 10, places: 2);
            $table->unsignedTinyInteger('cantidad_horas');
            $table->decimal('total_a_cobrar', total: 10, places: 2);
            $table->date('fecha_trabajada');

            $table->foreignId('id_profesional')->constrained(table: 'profesionales');

            $table->timestamps();

            $table->unique(['id_profesional', 'fecha_trabajada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horas_trabajadas');
    }
};
