<?php

namespace App\Services\Erp;

use App\Support\ConsultasErp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fuente ERP "consulta directa": corre la misma consulta OPENQUERY del Excel
 * contra SQL Server (conexión `erp`, sólo lectura) y sincroniza `lineas_pedido`.
 *
 * No reemplaza a `clientes` ni a `parametros_item` (en el libro original esas
 * hojas son manuales, no vienen de SQL Server) — esas se siguen refrescando con
 * `erp:importar` o se mantienen a mano.
 */
class ConsultaDirectaErp
{
    public function __construct(private LineasPedidoSync $sync) {}

    /** @return array{insertadas:int, actualizadas:int, desactivadas:int} */
    public function sincronizar(): array
    {
        return $this->sync->sincronizar($this->registros());
    }

    /** @return list<array<string,mixed>> */
    protected function registros(): array
    {
        $out = [];
        foreach ($this->filas() as $f) {
            $f = (array) $f;
            $pedido = trim((string) ($f['pedido'] ?? ''));
            $item = LineasPedidoSync::codigoItem($f['item'] ?? null);
            if ($pedido === '' || $item === '') {
                continue;
            }

            $out[] = [
                'pedido' => $pedido,
                'item' => $item,
                'bodega' => $this->str($f['bodega'] ?? null),
                'descripcion' => $this->str($f['descripcion'] ?? null),
                'grupo_item' => $this->str($f['grupoitem'] ?? null),
                'fecha_elaboracion' => LineasPedidoSync::aFecha($f['fechaelaboracion'] ?? null),
                'unidad_medida' => $this->str($f['unidadmedida'] ?? null),
                'cantidad_pedida' => LineasPedidoSync::aNumero($f['cantidadpedida'] ?? 0) ?? 0,
                'cantidad_despachada' => LineasPedidoSync::aNumero($f['cantidaddespachada'] ?? null),
                'fecha_requerida' => LineasPedidoSync::aFecha($f['fecharequerida'] ?? null),
                'fecha_despacho' => LineasPedidoSync::aFecha($f['fechadespacho'] ?? null),
                'precio_total_neto' => LineasPedidoSync::aNumero($f['preciototalneto'] ?? null),
                'nit_cliente' => LineasPedidoSync::textoNit($f['nitcliente'] ?? ''),
                'nombre_cliente' => $this->str($f['nombrecliente'] ?? null),
                'estado' => trim((string) ($f['estado'] ?? '')),
                'fecha_ultimo_movimiento' => LineasPedidoSync::aFecha($f['fechaultimomovimiento'] ?? null),
                'usuario' => $this->str($f['usuario'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Filas crudas del ERP. Aislado para poder testear sin SQL Server.
     *
     * En modo 'api' consulta el proyecto puente `api-datos-panal` (para
     * cuando la app corre fuera de la red interna, ej. Hostinger); en modo
     * 'db' (por defecto) se conecta directo a SQL Server.
     */
    protected function filas(): array
    {
        if (config('erp.modo') === 'api') {
            return Http::baseUrl(config('erp.api_url'))
                ->withToken(config('erp.api_token'))
                ->acceptJson()
                ->timeout(30)
                ->throw()
                ->get('/api/erp/pedidos-pl')
                ->json('data');
        }

        return DB::connection(config('erp.conexion', 'erp'))->select(ConsultasErp::pedidosPl());
    }

    private function str(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return $v === null || $v === '' ? null : (string) $v;
    }
}
