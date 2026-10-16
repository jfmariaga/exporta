<?php

namespace App\Services\Inventario;

use App\Models\InventarioSincbox;
use App\Models\InventarioSincboxDetalle;
use App\Models\Seguimiento;
use App\Support\EstadosPedido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lado de lectura del reparto de inventario: cuánto hay sincronizado por
 * ítem, cuánto ya está asignado a pedidos pendientes y cuánto queda
 * disponible. `asignado` se deriva en vivo de `seguimientos.inventario`
 * (única fuente de verdad del reparto, nunca un contador aparte).
 */
class DisponibilidadInventario
{
    public function cantidadTotal(string $item): float
    {
        return (float) (InventarioSincbox::where('item', $item)->value('cantidad_total') ?? 0);
    }

    /** Suma de inventario ya asignado a $item en pedidos pendientes distintos de $pedidoExcluido. */
    public function asignadoEnOtrosPedidos(string $item, ?string $pedidoExcluido = null): float
    {
        return (float) Seguimiento::query()
            ->join('lineas_pedido', function ($join) {
                $join->on('lineas_pedido.pedido', '=', 'seguimientos.pedido')
                    ->on('lineas_pedido.item', '=', 'seguimientos.item');
            })
            ->where('seguimientos.item', $item)
            ->when($pedidoExcluido, fn ($q) => $q->where('seguimientos.pedido', '!=', $pedidoExcluido))
            ->where('lineas_pedido.activo', true)
            ->whereIn('lineas_pedido.estado', EstadosPedido::POR_LIBERAR)
            ->whereNotNull('seguimientos.inventario')
            ->sum('seguimientos.inventario');
    }

    public function disponible(string $item, ?string $pedidoExcluido = null): float
    {
        return $this->cantidadTotal($item) - $this->asignadoEnOtrosPedidos($item, $pedidoExcluido);
    }

    /** Estados posibles del reparto de un ítem, de más a menos urgente. */
    private const ORDEN_ESTADOS = [
        'sobre_asignado' => 0,
        'sin_asignar' => 1,
        'en_reparto' => 2,
        'distribuido' => 3,
    ];

    /**
     * Estado de reparto de cada ítem sincronizado: `sobre_asignado`
     * (disponible < 0, una sync posterior trajo menos de lo ya repartido),
     * `sin_asignar` (nada repartido todavía), `en_reparto` (parcialmente
     * repartido, aún queda saldo) o `distribuido` (todo el pool ya está
     * asignado a pedidos pendientes). Ítems sin existencia ni asignación
     * no se listan (nada que reportar).
     *
     * @return Collection<int, array{item:string, cantidad_total:float, asignado:float, disponible:float, estado:string, sincronizado_en:?Carbon, detalle:Collection}>
     */
    public function estados(): Collection
    {
        $asignadoPorItem = Seguimiento::query()
            ->join('lineas_pedido', function ($join) {
                $join->on('lineas_pedido.pedido', '=', 'seguimientos.pedido')
                    ->on('lineas_pedido.item', '=', 'seguimientos.item');
            })
            ->where('lineas_pedido.activo', true)
            ->whereIn('lineas_pedido.estado', EstadosPedido::POR_LIBERAR)
            ->whereNotNull('seguimientos.inventario')
            ->groupBy('seguimientos.item')
            ->selectRaw('seguimientos.item as item, SUM(seguimientos.inventario) as asignado')
            ->pluck('asignado', 'item');

        $detallePorItem = InventarioSincboxDetalle::all()->groupBy('item');
        $tolerancia = 0.0005;

        return InventarioSincbox::all()
            ->map(function (InventarioSincbox $inv) use ($asignadoPorItem, $detallePorItem, $tolerancia) {
                $total = (float) $inv->cantidad_total;
                $asignado = (float) ($asignadoPorItem[$inv->item] ?? 0);
                $disponible = $total - $asignado;

                if ($total <= $tolerancia && $asignado <= $tolerancia) {
                    return null;
                }

                $estado = match (true) {
                    $disponible < -$tolerancia => 'sobre_asignado',
                    $asignado <= $tolerancia => 'sin_asignar',
                    $disponible <= $tolerancia => 'distribuido',
                    default => 'en_reparto',
                };

                return [
                    'item' => $inv->item,
                    'cantidad_total' => $total,
                    'asignado' => $asignado,
                    'disponible' => $disponible,
                    'estado' => $estado,
                    'sincronizado_en' => $inv->sincronizado_en,
                    'detalle' => $detallePorItem->get($inv->item, collect()),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => self::ORDEN_ESTADOS[$row['estado']])
            ->values();
    }
}
