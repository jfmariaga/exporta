<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro manual (como parametros_item): condición especial que debe
 * cumplirse para un ítem en un destino dado (ej. "GESTIONAR PEDIDO",
 * "PROGRAMA SUPERPACK"). Se siembra una vez desde
 * documentacion/ACTIVIDAD POR DESTINO.xlsx y luego se administra desde la UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades_destino', function (Blueprint $table) {
            $table->id();
            $table->string('item', 30);
            $table->string('destino', 80);
            $table->string('categoria', 80)->nullable();
            $table->string('actividad', 120)->nullable();
            $table->timestamps();
            $table->unique(['item', 'destino']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades_destino');
    }
};
