<?php

namespace App\Models;

use App\Support\EstadosPedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LineaPedido extends Model
{
    protected $table = 'lineas_pedido';

    protected $fillable = [
        'pedido',
        'bodega',
        'item',
        'descripcion',
        'grupo_item',
        'fecha_elaboracion',
        'unidad_medida',
        'cantidad_pedida',
        'cantidad_despachada',
        'fecha_requerida',
        'fecha_despacho',
        'precio_total_neto',
        'nit_cliente',
        'nombre_cliente',
        'estado',
        'fecha_ultimo_movimiento',
        'usuario',
        'activo',
        'importado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_elaboracion' => 'date',
            'fecha_requerida' => 'date',
            'fecha_despacho' => 'date',
            'fecha_ultimo_movimiento' => 'date',
            'importado_en' => 'datetime',
            'cantidad_pedida' => 'decimal:3',
            'cantidad_despachada' => 'decimal:3',
            'precio_total_neto' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    /**
     * Universo "pedidos por liberar": estado PL/PP, ítem que no empieza por 7, línea activa.
     *
     * @param  Builder<LineaPedido>  $query
     */
    public function scopePorLiberar(Builder $query): void
    {
        $query->whereIn('estado', EstadosPedido::POR_LIBERAR)
            ->where('item', 'not like', '7%')
            ->where('activo', true);
    }
}
