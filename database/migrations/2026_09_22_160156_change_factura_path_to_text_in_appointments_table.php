<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE appointments ALTER COLUMN factura_path TYPE TEXT');
            } elseif ($driver === 'mysql') {
                DB::statement('ALTER TABLE appointments MODIFY factura_path TEXT NULL');
            } elseif ($driver === 'sqlsrv') {
                DB::statement('ALTER TABLE appointments ALTER COLUMN factura_path VARCHAR(MAX) NULL');
            } else {
                Schema::table('appointments', function (Blueprint $table) {
                    $table->text('factura_path')->nullable()->change();
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error al cambiar factura_path a text: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE appointments ALTER COLUMN factura_path TYPE VARCHAR(255)');
            } elseif ($driver === 'mysql') {
                DB::statement('ALTER TABLE appointments MODIFY factura_path VARCHAR(255) NULL');
            }
        } catch (\Throwable $e) {}
    }
};
