<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'tipo_mercancia')) {
                $table->string('tipo_mercancia')->nullable()->after('categoria_rendimiento_id');
            }
            if (!Schema::hasColumn('appointments', 'factura_path')) {
                $table->string('factura_path')->nullable()->after('numero_factura');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'tipo_mercancia')) {
                $table->dropColumn('tipo_mercancia');
            }
            if (Schema::hasColumn('appointments', 'factura_path')) {
                $table->dropColumn('factura_path');
            }
        });
    }
};
