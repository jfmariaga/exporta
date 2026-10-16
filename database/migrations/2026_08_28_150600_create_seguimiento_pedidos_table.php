<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento a nivel de PEDIDO: fecha tentativa de cargue y estado de
 * seguimiento (Pendiente / En validación / Listo para cargar). La alerta
 * tipo semáforo y el comentario de correo se derivan de estos campos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('pedido', 20)->unique();
            $table->string('estado_seguimiento', 30)->default('Pendiente');
            $table->date('fecha_tentativa_cargue')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('estado_seguimiento');
            $table->index('fecha_tentativa_cargue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_pedidos');
    }
};
