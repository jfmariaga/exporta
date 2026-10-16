<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario de exportación fila a fila (SyncBox, Postgres, SÓLO LECTURA),
 * sin totalizar por ítem: una fila por bodega/ubicación/lote/bloqueado. Es
 * un snapshot — cada sincronización reemplaza todo el contenido, no se
 * acumula ni se guarda histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_sincbox_detalle', function (Blueprint $table) {
            $table->id();
            $table->string('item', 30);
            $table->string('warehouse', 30)->nullable();
            $table->string('ubication', 60)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->string('lote', 60)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('cantidad', 16, 3)->default(0);
            $table->boolean('bloqueado')->default(false);
            $table->date('creado')->nullable();
            $table->string('comentarios', 255)->nullable();
            $table->timestamp('sincronizado_en')->nullable();
            $table->timestamps();

            $table->index('item');
            $table->index('lote');
            $table->index('creado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_sincbox_detalle');
    }
};
