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
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();

            $table->string('dni', length: 8)->unique();
            $table->string('nombre', length: 30);
            $table->string('apellido', length: 30);
            $table->date('fecha_nac');
            $table->string('domicilio', length: 100);
            $table->string('telefono', length: 20);
            $table->string('profesion', length: 45);
            $table->string('actividad_fisica', length: 45);
            $table->boolean('es_adulto_mayor');
            $table->string('vive_con', length: 150)->nullable();
            $table->unsignedTinyInteger('sesiones_a_favor')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
