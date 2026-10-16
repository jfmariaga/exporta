<?php

namespace App\Services\Inventario;

use App\Models\InventarioSincbox;
use App\Models\InventarioSincboxDetalle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Escritura idempotente de `inventario_sincbox` (totales por ítem) e
 * `inventario_sincbox_detalle` (fila a fila, sin totalizar). Cada corrida
 * es un snapshot absoluto: los totales se SOBRESCRIBEN (nunca se acumulan)
 * y el detalle se REEMPLAZA por completo. Los códigos que ya no vienen en
 * el origen quedan en cero en el total (no se borran). Nunca toca
 * `seguimientos` — el reparto sigue siendo manual y validado contra
 * `inventario_sincbox`, ver {@see DisponibilidadInventario}.
 */
class InventarioSync
{
    public function __construct(private ConsultaExistenciasSyncBox $fuente) {}

    /** @return array{insertadas:int, actualizadas:int, puestas_en_cero:int, filas_detalle:int} */
    public function sincronizar(): array
    {
        $filasNormalizadas = $this->fuente->filasNormalizadas();

        if ($filasNormalizadas === []) {
            throw new RuntimeException('SyncBox no devolvió existencias; se aborta para no vaciar inventario_sincbox.');
        }

        $existencias = [];
        foreach ($filasNormalizadas as $f) {
            $existencias[$f['item']]['cantidad_total'] = ($existencias[$f['item']]['cantidad_total'] ?? 0.0) + $f['amount'];
        }

        return DB::transaction(function () use ($existencias, $filasNormalizadas) {
            $antes = InventarioSincbox::count();

            $ahora = now();
            $filasTotal = [];
            foreach ($existencias as $item => $datos) {
                $filasTotal[] = [
                    'item' => $item,
                    'cantidad_total' => round($datos['cantidad_total'], 3),
                    'sincronizado_en' => $ahora,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            $itemsPresentes = array_column($filasTotal, 'item');
            $puestasEnCero = InventarioSincbox::whereNotIn('item', $itemsPresentes)
                ->where('cantidad_total', '>', 0)
                ->update(['cantidad_total' => 0, 'sincronizado_en' => $ahora]);

            foreach (array_chunk($filasTotal, 500) as $lote) {
                InventarioSincbox::upsert(
                    $lote,
                    ['item'],
                    ['cantidad_total', 'sincronizado_en', 'updated_at'],
                );
            }

            $despues = InventarioSincbox::count();
            $insertadas = max(0, $despues - $antes);

            // Detalle fila a fila: snapshot completo, se reemplaza entero.
            InventarioSincboxDetalle::query()->delete();
            $filasDetalle = array_map(fn (array $f) => [
                'item' => $f['item'],
                'warehouse' => $f['warehouse'],
                'ubication' => $f['ubication'],
                'descripcion' => $f['description'],
                'lote' => $f['lote'],
                'product_id' => $f['product_id'],
                'cantidad' => round($f['amount'], 3),
                'bloqueado' => $f['is_blocked'],
                'creado' => $f['created'],
                'comentarios' => $f['comments'],
                'sincronizado_en' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $filasNormalizadas);

            foreach (array_chunk($filasDetalle, 500) as $lote) {
                InventarioSincboxDetalle::insert($lote);
            }

            return [
                'insertadas' => $insertadas,
                'actualizadas' => count($filasTotal) - $insertadas,
                'puestas_en_cero' => $puestasEnCero,
                'filas_detalle' => count($filasDetalle),
            ];
        });
    }
}
