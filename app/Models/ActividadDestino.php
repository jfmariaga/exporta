<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActividadDestino extends Model
{
    protected $table = 'actividades_destino';

    protected $fillable = [
        'item',
        'destino',
        'categoria',
        'actividad',
    ];
}
