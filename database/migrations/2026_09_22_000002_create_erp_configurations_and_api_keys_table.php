<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('erp_configurations')) {
            Schema::create('erp_configurations', function (Blueprint $table) {
                $table->id();
                $table->string('tipo_erp', 50)->default('profit'); // profit, sap, odoo, saint, excel, custom_sql
                $table->string('nombre_conexion', 100)->default('ERP Principal');
                $table->string('db_driver', 50)->default('sqlsrv');
                $table->string('db_host', 150)->nullable();
                $table->string('db_port', 10)->nullable();
                $table->string('db_database', 100)->nullable();
                $table->string('db_username', 100)->nullable();
                $table->text('db_password')->nullable();
                $table->json('mapeo_columnas')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamp('ultima_sincronizacion')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('erp_api_keys')) {
            Schema::create('erp_api_keys', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_cliente', 150);
                $table->string('api_key', 64)->unique();
                $table->boolean('activo')->default(true);
                $table->timestamp('ultimo_uso')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_api_keys');
        Schema::dropIfExists('erp_configurations');
    }
};