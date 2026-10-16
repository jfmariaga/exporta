<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inventario de exportación fila a fila (SyncBox, Postgres, sólo lectura):
 * una fila por bodega/ubicación/lote/bloqueado, sin totalizar por ítem.
 * Snapshot — se reemplaza por completo en cada sincronización.
 */
class InventarioSincboxDetalle extends Model
{
    protected $table = 'inventario_sincbox_detalle';

    protected $fillable = [
        'item',
        'warehouse',
        'ubication',
        'descripcion',
        'lote',
        'product_id',
        'cantidad',
        'bloqueado',
        'creado',
        'comentarios',
        'sincronizado_en',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'bloqueado' => 'boolean',
            'creado' => 'date',
            'sincronizado_en' => 'datetime',
        ];
    }
}
