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
        Schema::create('appointment_route_logs', function (Blueprint $table) {
            $table->id();
            $table->string('numero_oc'); // Referencia a la cita
            $table->string('estatus_anterior')->nullable(); // 'programada'
            $table->string('estatus_nuevo'); // 'en muelle', 'descargando', etc.
            $table->unsignedBigInteger('user_id')->nullable(); // Quién cambió el estatus
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_route_logs');
    }
};
