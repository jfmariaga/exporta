<?php

namespace App\Console\Commands;

use App\Services\Inventario\InventarioSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sincroniza `inventario_sincbox` en vivo desde SyncBox (Postgres).
 * Idempotente. Sólo lectura del origen.
 *
 * Requiere la extensión PHP `pdo_pgsql` y la conexión `inventario` configurada
 * (`.env` → INVENTARIO_HOST / INVENTARIO_DATABASE / INVENTARIO_USERNAME / INVENTARIO_PASSWORD).
 */
class SincronizarInventario extends Command
{
    protected $signature = 'inventario:sincronizar';

    protected $description = 'Sincroniza inventario_sincbox desde SyncBox (Postgres) por código de producto.';

    public function handle(InventarioSync $fuente): int
    {
        $this->info('Consultando SyncBox…');

        try {
            $r = $fuente->sincronizar();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Origen', 'Insertadas', 'Actualizadas', 'Puestas en cero', 'Filas de detalle'],
            [['inventario_sincbox', $r['insertadas'], $r['actualizadas'], $r['puestas_en_cero'], $r['filas_detalle']]],
        );
        $this->info('Sincronización completada.');

        return self::SUCCESS;
    }
}
