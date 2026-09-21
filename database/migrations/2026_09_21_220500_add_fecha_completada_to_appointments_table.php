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
            if (!Schema::hasColumn('appointments', 'fecha_completada')) {
                $table->timestamp('fecha_completada')->nullable()->after('observaciones');
            }
            if (!Schema::hasColumn('appointments', 'completada_por_nombre')) {
                $table->string('completada_por_nombre')->nullable()->after('fecha_completada');
            }
            if (!Schema::hasColumn('appointments', 'completada_por_user_id')) {
                $table->unsignedBigInteger('completada_por_user_id')->nullable()->after('completada_por_nombre');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'completada_por_user_id')) {
                $table->dropColumn('completada_por_user_id');
            }
            if (Schema::hasColumn('appointments', 'completada_por_nombre')) {
                $table->dropColumn('completada_por_nombre');
            }
            if (Schema::hasColumn('appointments', 'fecha_completada')) {
                $table->dropColumn('fecha_completada');
            }
        });
    }
};
