<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes_fijos', function (Blueprint $table) {
            $table->foreignId('id_pac_fijo_dual')
                ->nullable()
                ->after('id_paciente')
                ->constrained('pacientes_fijos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pacientes_fijos', function (Blueprint $table) {
            $table->dropForeign(['id_pac_fijo_dual']);
            $table->dropColumn('id_pac_fijo_dual');
        });
    }
};
