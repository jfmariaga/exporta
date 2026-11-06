<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial (append-only) de cada vez que alguien marca/desmarca la
 * condición "actividad por destino" como cumplida en una línea de pedido.
 * `actividad`/`usuario_nombre` quedan como snapshot (el catálogo o el
 * usuario pueden cambiar después sin alterar lo ya registrado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_destino_auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('pedido', 20);
            $table->string('item', 30);
            $table->string('destino', 80)->nullable();
            $table->string('actividad', 120)->nullable();
            $table->boolean('cumplida');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pedido', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_destino_auditorias');
    }
};
