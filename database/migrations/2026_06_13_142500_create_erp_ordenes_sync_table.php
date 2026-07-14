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
        Schema::create('erp_ordenes_sync', function (Blueprint $table) {
            $table->string('numero_oc')->primary();
            $table->string('fecha_emision')->nullable();
            $table->string('fecha_recepcion')->nullable();
            $table->string('proveedor')->nullable();
            $table->string('destino')->nullable();
            $table->longText('resumen_json')->nullable();
            $table->longText('detalles_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_ordenes_sync');
    }
};
