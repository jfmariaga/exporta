<?php

namespace App\Services;

use App\Support\EstadosPedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de "pedidos por liberar": líneas en estado PL/PP, sin ítems que
 * empiecen por 7, cruzadas con destino (por NIT) y con el factor de kilogramos
 * para calcular toneladas (cantidad_pedida * factor_kg / 1000).
 *
 * El seguimiento de fecha tentativa / estado / alerta es a nivel de PEDIDO
 * (tabla seguimiento_pedidos); el de estado de materiales / observaciones /
 * inventario es a nivel de LÍNEA (tabla seguimientos).
 */
class PedidosPorLiberarQuery
{
    /** Query base a nivel de línea. */
    public function baseQuery(): Builder
    {
        return DB::table('lineas_pedido as l')
            ->whereIn('l.estado', EstadosPedido::POR_LIBERAR)
            ->where('l.item', 'not like', '7%')
            ->where('l.activo', true)
            ->leftJoin('clientes as c', 'c.nit_cliente', '=', 'l.nit_cliente')
            ->leftJoin('parametros_item as p', 'p.item', '=', 'l.item')
            ->selectRaw(implode(', ', [
                'l.pedido', 'l.bodega', 'l.item', 'l.descripcion', 'l.grupo_item',
                'l.fecha_elaboracion', 'l.unidad_medida', 'l.cantidad_pedida',
                'l.cantidad_despachada', 'l.fecha_requerida', 'l.fecha_despacho',
                'l.precio_total_neto', 'l.nit_cliente', 'l.nombre_cliente', 'l.estado',
                'c.cliente',
                'COALESCE(c.destino, "#N/D") as destino',
                '(c.nit_cliente is null) as sin_cliente',
                'p.factor_kg', 'p.categoria',
                '(l.cantidad_pedida * p.factor_kg / 1000) as toneladas_linea',
                '(p.item is null) as sin_factor',
            ]));
    }

    /**
     * Resumen agrupado por pedido, con filtros y paginación.
     *
     * @param  array<string,mixed>  $filtros
     * @return LengthAwarePaginator<int, object>
     */
    public function resumenPorPedido(array $filtros = [], int $porPagina = 25): LengthAwarePaginator
    {
        $q = DB::table('lineas_pedido as l')
            ->whereIn('l.estado', EstadosPedido::POR_LIBERAR)
            ->where('l.item', 'not like', '7%')
            ->where('l.activo', true)
            ->leftJoin('clientes as c', 'c.nit_cliente', '=', 'l.nit_cliente')
            ->leftJoin('parametros_item as p', 'p.item', '=', 'l.item')
            ->leftJoin('seguimientos as sl', function ($j) {
                $j->on('sl.pedido', '=', 'l.pedido')->on('sl.item', '=', 'l.item');
            })
            ->leftJoin('seguimiento_pedidos as sp', 'sp.pedido', '=', 'l.pedido')
            ->groupBy('l.pedido')
            ->selectRaw(implode(', ', [
                'l.pedido',
                'MAX(l.nit_cliente) as nit_cliente',
                'MAX(l.nombre_cliente) as nombre_cliente',
                'COALESCE(MAX(c.destino), "#N/D") as destino',
                'MAX(l.estado) as estado',
                'MIN(l.fecha_elaboracion) as fecha_solicitud',
                'SUM(l.cantidad_pedida) as cantidad_total',
                'SUM(l.cantidad_pedida * p.factor_kg) / 1000 as toneladas_total',
                'MAX(p.item is null) as toneladas_incompleto',
                'SUM(sl.inventario) as inventario_total',
                'CASE WHEN SUM(l.cantidad_pedida) > 0 AND SUM(sl.inventario) IS NOT NULL THEN SUM(sl.inventario) * 100.0 / SUM(l.cantidad_pedida) END as porcentaje_cumplimiento',
                'MAX(sp.estado_seguimiento) as estado_seguimiento',
                'MAX(sp.fecha_tentativa_cargue) as fecha_tentativa_cargue',
                'EXISTS(SELECT 1 FROM seguimiento_pedidos x WHERE x.pedido = l.pedido) as revisado',
            ]));

        if (! empty($filtros['destino'])) {
            $q->where('c.destino', $filtros['destino']);
        }
        if (! empty($filtros['estado'])) {
            $q->where('l.estado', $filtros['estado']);
        }
        if (! empty($filtros['q'])) {
            $termino = '%'.$filtros['q'].'%';
            $q->where(fn ($w) => $w->where('l.pedido', 'like', $termino)->orWhere('l.nit_cliente', 'like', $termino));
        }

        $q->orderBy('l.pedido');

        $paginador = $q->paginate($porPagina);

        if (! empty($filtros['revision'])) {
            $quiereRevisado = $filtros['revision'] === 'REVISADO';
            $paginador->setCollection(
                $paginador->getCollection()->filter(fn ($f) => (bool) $f->revisado === $quiereRevisado)->values()
            );
        }

        $paginador->getCollection()->transform(function ($fila) {
            $fila->estado_revision = $fila->revisado ? 'REVISADO' : 'POR REVISAR';
            $fila->estado_seguimiento ??= 'Pendiente';
            $fila->alerta = $this->alerta($fila->estado_seguimiento);

            return $fila;
        });

        return $paginador;
    }

    /**
     * Detalle de un pedido: líneas (con seguimiento de línea) + totales +
     * seguimiento de pedido.
     *
     * @return array{lineas: Collection<int, object>, totales: object}|null
     */
    public function detallePedido(string $pedido): ?array
    {
        $lineas = $this->baseQuery()
            ->leftJoin('seguimientos as s', function ($join) {
                $join->on('s.pedido', '=', 'l.pedido')->on('s.item', '=', 'l.item');
            })
            ->where('l.pedido', $pedido)
            ->addSelect(
                's.estado_materiales', 's.observaciones', 's.fecha_programa', 's.inventario',
            )
            ->selectRaw('CASE WHEN l.cantidad_pedida > 0 AND s.inventario IS NOT NULL THEN s.inventario * 100.0 / l.cantidad_pedida END as porcentaje_cumplimiento')
            ->orderBy('l.item')
            ->get();

        if ($lineas->isEmpty()) {
            return null;
        }

        $primera = $lineas->first();
        $sinFactor = $lineas->contains(fn ($l) => (bool) $l->sin_factor);

        $sp = DB::table('seguimiento_pedidos')->where('pedido', $pedido)->first();
        $estadoSeg = $sp->estado_seguimiento ?? 'Pendiente';
        $fechaTentativa = $sp->fecha_tentativa_cargue ?? null;
        $inventarioTotal = $lineas->sum(fn ($l) => (float) $l->inventario);
        $cantidadTotal = $lineas->sum(fn ($l) => (float) $l->cantidad_pedida);

        $totales = (object) [
            'pedido' => $pedido,
            'nit_cliente' => $primera->nit_cliente,
            'nombre_cliente' => $primera->nombre_cliente,
            'destino' => $primera->destino,
            'estado' => $primera->estado,
            'fecha_solicitud' => $lineas->min('fecha_elaboracion'),
            'cantidad_total' => $cantidadTotal,
            'toneladas_total' => round($lineas->sum(fn ($l) => (float) $l->toneladas_linea), 5),
            'toneladas_incompleto' => $sinFactor,
            'inventario_total' => $inventarioTotal ?: null,
            'porcentaje_cumplimiento' => $inventarioTotal > 0 && $cantidadTotal > 0 ? round($inventarioTotal / $cantidadTotal * 100, 2) : null,
            'estado_seguimiento' => $estadoSeg,
            'fecha_tentativa_cargue' => $fechaTentativa,
            'alerta' => $this->alerta($estadoSeg),
            'estado_revision' => $sp ? 'REVISADO' : 'POR REVISAR',
        ];

        return ['lineas' => $lineas, 'totales' => $totales];
    }

    private function alerta(?string $estado): string
    {
        return match ($estado) {
            'Pendiente' => '🔴',
            'En validación' => '🟡',
            'Listo para cargar' => '✅',
            default => '',
        };
    }
}
