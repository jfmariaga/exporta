<?php

namespace App\Console\Commands;

use App\Services\Erp\ConsultaDirectaErp;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sincroniza `lineas_pedido` en vivo desde el ERP (SQL Server), corriendo la
 * misma consulta OPENQUERY del Excel. Idempotente. Sólo lectura del ERP.
 *
 * Requiere la extensión PHP `pdo_sqlsrv` y la conexión `erp` configurada
 * (`.env` → ERP_HOST / ERP_DATABASE / ERP_USERNAME / ERP_PASSWORD).
 */
class SincronizarErp extends Command
{
    protected $signature = 'erp:sincronizar';

    protected $description = 'Sincroniza lineas_pedido desde el ERP (SQL Server) vía OPENQUERY. No usa archivo.';

    public function handle(ConsultaDirectaErp $fuente): int
    {
        $this->info('Consultando el ERP…');

        try {
            $r = $fuente->sincronizar();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Origen', 'Insertadas', 'Actualizadas', 'Desactivadas'],
            [['lineas_pedido', $r['insertadas'], $r['actualizadas'], $r['desactivadas']]],
        );
        $this->info('Sincronización completada.');

        return self::SUCCESS;
    }
}
