<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades_pacientes', function (Blueprint $table) {
            $table->dropForeign(['id_actividad']);
            $table->dropForeign(['id_paciente']);
            $table->dropForeign(['id_paciente_casual']);

            $table->dropUnique('act_pac_fecha_unique');
            $table->dropUnique('act_casual_fecha_unique');
            $table->dropColumn('fecha_comienzo');

            $table->foreign('id_actividad')->references('id')->on('actividades');
            $table->foreign('id_paciente')->references('id')->on('pacientes');
            $table->foreign('id_paciente_casual')->references('id')->on('pacientes_casuales');
        });
    }

    public function down(): void
    {
        Schema::table('actividades_pacientes', function (Blueprint $table) {
            $table->dropForeign(['id_actividad']);
            $table->dropForeign(['id_paciente']);
            $table->dropForeign(['id_paciente_casual']);

            $table->date('fecha_comienzo')->after('id');
            $table->unique(['id_actividad', 'id_paciente', 'fecha_comienzo'], 'act_pac_fecha_unique');
            $table->unique(['id_actividad', 'id_paciente_casual', 'fecha_comienzo'], 'act_casual_fecha_unique');

            $table->foreign('id_actividad')->references('id')->on('actividades');
            $table->foreign('id_paciente')->references('id')->on('pacientes');
            $table->foreign('id_paciente_casual')->references('id')->on('pacientes_casuales');
        });
    }
};
