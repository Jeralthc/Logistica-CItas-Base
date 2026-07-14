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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            // Datos que vienen del ERP (SQL Server)
            $table->string('numero_oc')->index(); 
            $table->string('proveedor');
            
            // Datos de la cita en la Web (Postgres)
            $table->dateTime('fecha_cita');
            $table->integer('muelle_asignado');
            $table->string('estatus')->default('programada'); // programada, en muelle, finalizada, cancelada
            
            // Relación: Quién registró la cita
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};