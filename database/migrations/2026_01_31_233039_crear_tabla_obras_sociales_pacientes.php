<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('obras_sociales_pacientes', function (Blueprint $table) {
            $table->id();

            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();

            $table->foreignId('id_obra_social')->nullable()->constrained(table: 'obras_sociales');
            $table->string('nombre_os', 30)->nullable();
            $table->foreignId('id_paciente')->constrained(table: 'pacientes');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE obras_sociales_pacientes ADD CONSTRAINT chk_afiliacion_xor CHECK (
                (id_obra_social IS NOT NULL AND nombre_os IS NULL)
                OR (id_obra_social IS NULL AND nombre_os IS NOT NULL)
            )');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obras_sociales_pacientes');
    }
};
