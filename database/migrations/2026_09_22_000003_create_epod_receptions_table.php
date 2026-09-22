<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('epod_receptions')) {
            Schema::create('epod_receptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appointment_id')->unique();
                $table->longText('chofer_firma_base64')->nullable();
                $table->string('chofer_nombre')->nullable();
                $table->string('chofer_cedula', 30)->nullable();
                $table->string('recepcionista_nombre')->nullable();
                $table->unsignedBigInteger('recepcionista_user_id')->nullable();
                $table->string('estado_mercancia', 30)->default('conforme'); // conforme, discrepancia, danada
                $table->text('observaciones_recepcion')->nullable();
                $table->json('fotos_evidencia')->nullable();
                $table->timestamp('fecha_firma')->nullable();
                $table->timestamps();

                $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('epod_receptions');
    }
};