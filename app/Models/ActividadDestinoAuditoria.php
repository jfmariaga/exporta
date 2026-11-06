<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActividadDestinoAuditoria extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pedido',
        'item',
        'destino',
        'actividad',
        'cumplida',
        'usuario_id',
        'usuario_nombre',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cumplida' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
