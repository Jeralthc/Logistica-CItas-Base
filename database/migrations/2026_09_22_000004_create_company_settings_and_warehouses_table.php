<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_settings')) {
            Schema::create('company_settings', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_empresa', 150)->default('Sistema de Gestión Logística');
                $table->string('rif_empresa', 30)->nullable();
                $table->string('logo_path')->nullable();
                $table->string('color_primario', 20)->default('#0284c7');
                $table->string('zona_horaria', 50)->default('America/Caracas');
                $table->string('moneda', 10)->default('USD');
                $table->string('email_contacto', 100)->nullable();
                $table->string('telefono_contacto', 50)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('codigo', 30)->unique();
                $table->string('direccion')->nullable();
                $table->integer('muelles_totales')->default(4);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('company_settings');
    }
};