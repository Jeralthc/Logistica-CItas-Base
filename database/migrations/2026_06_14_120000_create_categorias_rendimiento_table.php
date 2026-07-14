<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_rendimiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->integer('tiempo_fijo');              // T_fijo en minutos
            $table->decimal('velocidad_descarga', 4, 2); // ton/min
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_rendimiento');
    }
};
