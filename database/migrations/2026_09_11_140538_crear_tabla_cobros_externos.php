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
        Schema::create('cobros_externos', function (Blueprint $table) {
            $table->id();

            $table->decimal('monto', total: 10, places: 2);

            $table->foreignId('id_act_pac')->constrained(table: 'actividades_pacientes');

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cobros_externos');
    }
};
