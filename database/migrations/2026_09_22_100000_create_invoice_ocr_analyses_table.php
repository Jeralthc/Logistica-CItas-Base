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
        if (!Schema::hasTable('invoice_ocr_analyses')) {
            Schema::create('invoice_ocr_analyses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appointment_id')->index();
                $table->string('numero_oc', 50)->index();
                $table->string('numero_factura_extraido', 50)->nullable();
                $table->string('rif_emisor_extraido', 30)->nullable();
                $table->string('nombre_emisor_extraido', 150)->nullable();
                $table->date('fecha_emision_extraida')->nullable();
                $table->decimal('subtotal_extraido', 14, 2)->nullable();
                $table->decimal('iva_extraido', 14, 2)->nullable();
                $table->decimal('total_factura_extraido', 14, 2)->nullable();
                $table->decimal('total_odc', 14, 2)->nullable();
                $table->decimal('diferencia_total', 14, 2)->nullable();
                $table->string('estatus_conciliacion', 30)->default('pendiente'); // conforme, discrepancia, pendiente, error
                $table->string('resumen_discrepancias')->nullable(); // Texto corto de discrepancias
                $table->json('datos_factura_json')->nullable(); // Renglones de factura
                $table->json('conciliacion_json')->nullable(); // Tabla comparativa ODC vs Factura
                $table->longText('raw_text')->nullable();
                $table->timestamps();

                $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_ocr_analyses');
    }
};
