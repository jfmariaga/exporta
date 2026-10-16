<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Existencias de exportación por código de producto (SyncBox, Postgres),
 * de SÓLO LECTURA. `cantidad_total` se sobrescribe en cada sincronización
 * (es un snapshot, no un acumulador); `detalle` guarda el desglose por
 * bodega/ubicación/lote para que planeación vea dónde está el stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_sincbox', function (Blueprint $table) {
            $table->id();
            $table->string('item', 30)->unique();
            $table->decimal('cantidad_total', 16, 3)->default(0);
            $table->json('detalle')->nullable();
            $table->timestamp('sincronizado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_sincbox');
    }
};
