<?php

namespace App\Models;

use App\Enums\EstadoSeguimiento;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seguimiento a nivel de pedido: fecha tentativa de cargue y estado de
 * seguimiento. Deriva la alerta (semáforo) y el comentario de correo.
 */
class SeguimientoPedido extends Model
{
    protected $table = 'seguimiento_pedidos';

    protected $fillable = [
        'pedido',
        'estado_seguimiento',
        'fecha_tentativa_cargue',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_tentativa_cargue' => 'date',
            'estado_seguimiento' => EstadoSeguimiento::class,
        ];
    }

    /** @return BelongsTo<User, SeguimientoPedido> */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    protected function alerta(): Attribute
    {
        return Attribute::get(fn (): string => $this->estado_seguimiento?->alerta() ?? '');
    }

    protected function comentarioCorreo(): Attribute
    {
        return Attribute::get(fn (): string => $this->fecha_tentativa_cargue
            ? 'Pedido disponible para cargue sobre el '.$this->fecha_tentativa_cargue->format('d/m/Y')
            : 'PENDIENTE POR DEFINIR FECHA');
    }
}
