<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento a nivel de LÍNEA (pedido + item): estado de materiales (lista
 * cerrada), observaciones, fecha programada e inventario. El % de cumplimiento
 * se deriva (inventario / cantidad_pedida). No lo toca el importador del ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();
            $table->string('pedido', 20);
            $table->string('item', 30);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('cantidad', 16, 3)->nullable();
            $table->string('estado_materiales', 120)->nullable();
            $table->text('observaciones')->nullable();
            $table->date('fecha_programa')->nullable();
            $table->decimal('inventario', 16, 3)->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pedido', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos');
    }
};
