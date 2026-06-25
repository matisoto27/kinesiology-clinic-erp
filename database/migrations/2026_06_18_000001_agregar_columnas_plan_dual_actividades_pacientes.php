<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades_pacientes', function (Blueprint $table) {
            $table->unsignedTinyInteger('frecuencia_total_dual')->nullable()->after('id_paciente_casual');
            $table->foreignId('id_act_pac_dual')
                ->nullable()
                ->after('frecuencia_total_dual')
                ->constrained('actividades_pacientes');
            $table->boolean('plan_dual_pendiente')->default(false)->after('id_act_pac_dual');
        });
    }

    public function down(): void
    {
        Schema::table('actividades_pacientes', function (Blueprint $table) {
            $table->dropForeign(['id_act_pac_dual']);
            $table->dropColumn(['frecuencia_total_dual', 'id_act_pac_dual', 'plan_dual_pendiente']);
        });
    }
};
