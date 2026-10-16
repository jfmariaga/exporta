<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espejo de la hoja Parametros.
 * `factor_kg` = kilogramos de producto por unidad de venta (por caja);
 * las toneladas de una línea = cantidad_pedida * factor_kg / 1000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_item', function (Blueprint $table) {
            $table->id();
            $table->string('item', 30)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('factor_kg', 14, 6)->nullable();
            $table->string('categoria', 80)->nullable();
            $table->integer('embalaje')->nullable();
            $table->decimal('volumen', 16, 4)->nullable();
            $table->integer('num_tendidos')->nullable();
            $table->integer('cajas_pallet')->nullable();
            $table->decimal('altura_caja', 8, 2)->nullable();
            $table->decimal('cajas_por_nivel', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_item');
    }
};
