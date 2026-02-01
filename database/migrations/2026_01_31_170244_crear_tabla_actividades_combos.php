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
        Schema::create('actividades_combos', function (Blueprint $table) {
            $table->id();
            $table->boolean('activo')->default(true);

            $table->foreignId('id_actividad')->constrained(table: 'actividades');
            $table->foreignId('id_combo')->constrained(table: 'combos');

            $table->unique(['id_actividad', 'id_combo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividades_combos');
    }
};
