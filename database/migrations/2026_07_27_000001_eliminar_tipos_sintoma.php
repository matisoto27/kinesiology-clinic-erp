<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sintomas', function (Blueprint $table) {
            $table->dropForeign(['id_tipo']);
            $table->dropColumn('id_tipo');
        });

        Schema::dropIfExists('tipos_sintoma');
    }

    public function down(): void
    {
        Schema::create('tipos_sintoma', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->boolean('activo')->default(true);
        });

        Schema::table('sintomas', function (Blueprint $table) {
            $table->foreignId('id_tipo')->after('activo')->constrained(table: 'tipos_sintoma');
        });
    }
};
