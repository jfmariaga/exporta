<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Existencias de exportación totalizadas por código de producto (SyncBox,
 * Postgres, sólo lectura). `item` se cruza contra
 * `lineas_pedido.item`/`seguimientos.item`. El detalle fila a fila (bodega,
 * ubicación, lote) vive en {@see InventarioSincboxDetalle}.
 */
class InventarioSincbox extends Model
{
    protected $table = 'inventario_sincbox';

    protected $fillable = [
        'item',
        'cantidad_total',
        'sincronizado_en',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_total' => 'decimal:3',
            'sincronizado_en' => 'datetime',
        ];
    }
}
