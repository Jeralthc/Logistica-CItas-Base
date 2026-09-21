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
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'es_traslado_interno')) {
                $table->boolean('es_traslado_interno')->default(false)->index();
            }
            if (!Schema::hasColumn('appointments', 'galpon_origen')) {
                $table->string('galpon_origen')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'es_galpon')) {
                $table->boolean('es_galpon')->default(false)->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'es_traslado_interno')) {
                $table->dropColumn('es_traslado_interno');
            }
            if (Schema::hasColumn('appointments', 'galpon_origen')) {
                $table->dropColumn('galpon_origen');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'es_galpon')) {
                $table->dropColumn('es_galpon');
            }
        });
    }
};
