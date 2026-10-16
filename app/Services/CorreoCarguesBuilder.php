<?php

namespace App\Services;

use App\Support\EstadosPedido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arma el cuerpo del correo de cargues (equivalente a la macro PrepararCorreo del MVP):
 * lista los pedidos por liberar que tienen fecha tentativa de cargue (a nivel de pedido),
 * con el detalle de sus líneas.
 */
class CorreoCarguesBuilder
{
    /** @param  array<string,mixed>  $filtros */
    public function __construct(private array $filtros = []) {}

    /** @return Collection<string, Collection<int, object>> */
    public function grupos(): Collection
    {
        return DB::table('lineas_pedido as l')
            ->join('seguimiento_pedidos as sp', 'sp.pedido', '=', 'l.pedido')
            ->leftJoin('clientes as c', 'c.nit_cliente', '=', 'l.nit_cliente')
            ->leftJoin('seguimientos as s', function ($j) {
                $j->on('s.pedido', '=', 'l.pedido')->on('s.item', '=', 'l.item');
            })
            ->whereIn('l.estado', EstadosPedido::POR_LIBERAR)
            ->where('l.item', 'not like', '7%')
            ->where('l.activo', true)
            ->whereNotNull('sp.fecha_tentativa_cargue')
            ->when(! empty($this->filtros['destino']), fn ($w) => $w->where('c.destino', $this->filtros['destino']))
            ->when(! empty($this->filtros['desde']), fn ($w) => $w->whereDate('sp.fecha_tentativa_cargue', '>=', $this->filtros['desde']))
            ->when(! empty($this->filtros['hasta']), fn ($w) => $w->whereDate('sp.fecha_tentativa_cargue', '<=', $this->filtros['hasta']))
            ->orderBy('l.pedido')->orderBy('l.item')
            ->selectRaw(implode(', ', [
                'l.pedido', 'l.item', 'l.descripcion', 'l.cantidad_pedida',
                'l.nombre_cliente', 'COALESCE(c.destino, "#N/D") as destino',
                'sp.fecha_tentativa_cargue', 'sp.estado_seguimiento',
                's.estado_materiales', 's.observaciones', 's.fecha_programa', 's.inventario',
            ]))
            ->get()
            ->groupBy('pedido');
    }

    private function frase(?string $fecha): string
    {
        return $fecha
            ? 'Pedido disponible para cargue sobre el '.Carbon::parse($fecha)->format('d/m/Y')
            : 'PENDIENTE POR DEFINIR FECHA';
    }

    private function cant(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    public function html(): string
    {
        $grupos = $this->grupos();
        if ($grupos->isEmpty()) {
            return '<p>No hay pedidos con fecha tentativa de cargue.</p>';
        }

        $out = '<p>Buenos días,</p><p>Relacionamos pedidos con fecha tentativa de cargue:</p>';
        foreach ($grupos as $pedido => $lineas) {
            $primera = $lineas->first();
            $out .= "<h3>Pedido {$pedido} — ".e($primera->nombre_cliente).' ('.e($primera->destino).')</h3>';
            $out .= '<p>'.e($this->frase($primera->fecha_tentativa_cargue)).'</p>';
            $out .= '<table border="1" cellspacing="0" cellpadding="4"><tr>'
                .'<th>Ítem</th><th>Descripción</th><th>Cantidad</th><th>Inventario</th><th>Estado materiales</th><th>Fecha programada</th><th>Observaciones</th></tr>';
            foreach ($lineas as $l) {
                $out .= '<tr>'
                    .'<td>'.e($l->item).'</td>'
                    .'<td>'.e($l->descripcion).'</td>'
                    .'<td>'.e($this->cant((float) $l->cantidad_pedida)).'</td>'
                    .'<td>'.e($l->inventario !== null ? $this->cant((float) $l->inventario) : '—').'</td>'
                    .'<td>'.e((string) $l->estado_materiales).'</td>'
                    .'<td>'.e($l->fecha_programa ? Carbon::parse($l->fecha_programa)->format('d/m/Y') : '—').'</td>'
                    .'<td>'.e((string) $l->observaciones).'</td>'
                    .'</tr>';
            }
            $out .= '</table>';
        }

        return $out;
    }

    public function texto(): string
    {
        $grupos = $this->grupos();
        if ($grupos->isEmpty()) {
            return 'No hay pedidos con fecha tentativa de cargue.';
        }

        $out = ['Buenos días,', '', 'Relacionamos pedidos con fecha tentativa de cargue:', ''];
        foreach ($grupos as $pedido => $lineas) {
            $primera = $lineas->first();
            $out[] = "Pedido {$pedido} — {$primera->nombre_cliente} ({$primera->destino})";
            $out[] = $this->frase($primera->fecha_tentativa_cargue);
            foreach ($lineas as $l) {
                $out[] = "  - {$l->item}  {$l->descripcion}  x{$this->cant((float) $l->cantidad_pedida)}  [{$l->estado_materiales}]";
            }
            $out[] = '';
        }

        return implode("\n", $out);
    }
}
