<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('numero_oc')->nullable()->index();
            $table->string('proveedor')->nullable();
            $table->string('email_destino')->index();
            $table->string('vendedor_nombre')->nullable();
            $table->string('tipo_evento')->default('odc_habilitada'); // odc_habilitada, reactivacion, cita_confirmada
            $table->enum('estatus', ['exitoso', 'error'])->default('exitoso')->index();
            $table->text('error_mensaje')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
