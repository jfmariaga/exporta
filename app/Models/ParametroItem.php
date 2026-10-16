<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametroItem extends Model
{
    protected $table = 'parametros_item';

    protected $fillable = [
        'item',
        'descripcion',
        'factor_kg',
        'categoria',
        'embalaje',
        'volumen',
        'num_tendidos',
        'cajas_pallet',
        'altura_caja',
        'cajas_por_nivel',
    ];

    protected function casts(): array
    {
        return [
            'factor_kg' => 'decimal:6',
            'volumen' => 'decimal:4',
            'altura_caja' => 'decimal:2',
            'cajas_por_nivel' => 'decimal:4',
        ];
    }
}
