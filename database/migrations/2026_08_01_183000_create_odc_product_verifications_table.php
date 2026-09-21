<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odc_product_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('numero_oc');
            $table->string('codigo_producto');
            $table->boolean('revisado')->default(true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->unique(['numero_oc', 'codigo_producto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odc_product_verifications');
    }
};
