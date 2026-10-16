<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El detalle fila a fila pasó a su propia tabla `inventario_sincbox_detalle`
 * (consultable/filtrable), así que esta columna JSON queda redundante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_sincbox', function (Blueprint $table) {
            $table->dropColumn('detalle');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_sincbox', function (Blueprint $table) {
            $table->json('detalle')->nullable();
        });
    }
};
