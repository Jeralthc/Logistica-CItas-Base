<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('cedula')->unique();
            $table->enum('tipo', ['receptor', 'carga']);
            $table->boolean('disponible')->default(true);
            $table->string('turno')->default('diurno'); // diurno, nocturno
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operarios');
    }
};
