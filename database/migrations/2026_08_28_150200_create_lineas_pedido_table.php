<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espejo de la hoja PedidosVenta / "Pedidos Liberar" del ERP.
 * `item` y `nit_cliente` se guardan como texto (preservan ceros a la izquierda
 * y caracteres no numéricos). El ERP es de sólo lectura: esta tabla sólo la
 * escribe el importador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineas_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('pedido', 20);
            $table->string('bodega', 10)->nullable();
            $table->string('item', 30);
            $table->string('descripcion', 255)->nullable();
            $table->string('grupo_item', 120)->nullable();
            $table->date('fecha_elaboracion')->nullable();
            $table->string('unidad_medida', 10)->nullable();
            $table->decimal('cantidad_pedida', 16, 3)->default(0);
            $table->decimal('cantidad_despachada', 16, 3)->nullable();
            $table->date('fecha_requerida')->nullable();
            $table->date('fecha_despacho')->nullable();
            $table->decimal('precio_total_neto', 18, 4)->nullable();
            $table->string('nit_cliente', 30);
            $table->string('nombre_cliente', 255)->nullable();
            $table->string('estado', 4);
            $table->date('fecha_ultimo_movimiento')->nullable();
            $table->string('usuario', 40)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('importado_en')->nullable();
            $table->timestamps();

            $table->unique(['pedido', 'item']);
            $table->index('estado');
            $table->index('nit_cliente');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_pedido');
    }
};
