<?php

namespace App\Services\Inventario;

use App\Services\Erp\LineasPedidoSync;
use App\Support\ConsultasInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Consulta de sólo lectura contra Postgres (SyncBox) y normalización del
 * inventario de exportación: fila a fila (por bodega/ubicación/lote) y
 * totalizado por código de producto.
 */
class ConsultaExistenciasSyncBox
{
    /**
     * Filas normalizadas, una por cada (bodega, ubicación, código, lote,
     * bloqueado) del origen, con el código ya acolchado igual que los
     * ítems del ERP. Base tanto del detalle fila a fila como del total
     * por ítem.
     *
     * @return list<array{item: string, warehouse: ?string, ubication: ?string, description: ?string, lote: ?string, product_id: mixed, amount: float, is_blocked: bool, created: ?string, comments: ?string}>
     */
    public function filasNormalizadas(): array
    {
        $out = [];
        foreach ($this->filas() as $f) {
            $f = (array) $f;
            $item = LineasPedidoSync::codigoItem($f['code'] ?? null);
            if ($item === '') {
                continue;
            }

            $out[] = [
                'item' => $item,
                'warehouse' => $this->str($f['warehouse'] ?? null),
                'ubication' => $this->str($f['ubication'] ?? null),
                'description' => $this->str($f['description'] ?? null),
                'lote' => $this->str($f['lote'] ?? null),
                'product_id' => $f['product_id'] ?? null,
                'amount' => LineasPedidoSync::aNumero($f['amount'] ?? 0) ?? 0.0,
                'is_blocked' => (bool) ($f['is_blocked'] ?? false),
                'created' => $this->str($f['created'] ?? null),
                'comments' => $this->str($f['comments'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Totaliza `amount` por código y conserva el detalle de cada fila
     * origen (bodega, ubicación, lote, bloqueado) para mostrarlo en la UI.
     *
     * @return array<string, array{cantidad_total: float, detalle: list<array<string, mixed>>}>
     */
    public function existenciasPorItem(): array
    {
        $out = [];
        foreach ($this->filasNormalizadas() as $f) {
            $item = $f['item'];
            $out[$item]['cantidad_total'] = ($out[$item]['cantidad_total'] ?? 0.0) + $f['amount'];
            $out[$item]['detalle'][] = collect($f)->except('item')->all();
        }

        return $out;
    }

    /**
     * Filas crudas de SyncBox. Aislado para poder testear sin Postgres.
     *
     * En modo 'api' consulta el proyecto puente `api-datos-panal` (para
     * cuando la app corre fuera de la red interna, ej. Hostinger); en modo
     * 'db' (por defecto) se conecta directo a Postgres.
     */
    protected function filas(): array
    {
        if (config('inventario.modo') === 'api') {
            return Http::baseUrl(config('inventario.api_url'))
                ->withToken(config('inventario.api_token'))
                ->acceptJson()
                ->timeout(30)
                ->throw()
                ->get('/api/inventario/existencias')
                ->json('data');
        }

        return DB::connection(config('inventario.conexion', 'inventario'))
            ->select(ConsultasInventario::existenciasExportacion());
    }

    private function str(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return $v === null || $v === '' ? null : (string) $v;
    }
}
