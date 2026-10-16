<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seguimiento a nivel de línea (pedido + item).
 */
class Seguimiento extends Model
{
    protected $table = 'seguimientos';

    protected $fillable = [
        'pedido',
        'item',
        'descripcion',
        'cantidad',
        'estado_materiales',
        'observaciones',
        'fecha_programa',
        'inventario',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'inventario' => 'decimal:3',
            'fecha_programa' => 'date',
        ];
    }

    /** @return BelongsTo<User, Seguimiento> */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** % de cumplimiento = inventario / cantidad pedida * 100. Null si no hay cantidad. */
    protected function porcentajeCumplimiento(): Attribute
    {
        return Attribute::get(function (): ?float {
            $inv = $this->inventario === null ? null : (float) $this->inventario;
            $cant = (float) $this->cantidad;

            return $inv !== null && $cant > 0 ? round($inv / $cant * 100, 2) : null;
        });
    }
}
