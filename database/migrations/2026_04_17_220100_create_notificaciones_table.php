<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero_oc')->index();
            $table->string('proveedor');
            $table->string('tipo')->default('nueva_oc');
            $table->dateTime('fecha_oc');
            $table->dateTime('fecha_recepcion')->nullable();
            $table->string('status_erp')->nullable();
            $table->boolean('leida')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
