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
        Schema::create('system_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Quién lo hizo (nullable para logs del sistema automáticos)
            $table->string('user_name')->nullable(); // Snapshot del nombre
            $table->string('user_role')->nullable(); // Snapshot del rol/cargo
            $table->string('module'); // Módulo afectado: 'Citas', 'Usuarios', 'Auth', etc.
            $table->string('action'); // 'crear', 'actualizar', 'eliminar', 'reprogramar', 'login'
            $table->string('auditable_type')->nullable(); // App\Models\User, etc.
            $table->string('auditable_id')->nullable(); // ID de la cita, usuario, etc. (puede ser string si es numero de OC)
            $table->text('motive')->nullable(); // El motivo obligatorio
            $table->jsonb('old_values')->nullable(); // Estado anterior
            $table->jsonb('new_values')->nullable(); // Estado nuevo
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent(); // Solo created_at, es inmutable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
    }
};
