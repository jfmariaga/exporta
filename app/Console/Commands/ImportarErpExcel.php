<?php

namespace App\Console\Commands;

use App\Services\ImportadorErp;
use Illuminate\Console\Command;
use Throwable;

class ImportarErpExcel extends Command
{
    protected $signature = 'erp:importar {ruta : Ruta al archivo .xlsx del ERP}';

    protected $description = 'Importa la hoja PedidosVenta/"Pedidos Liberar" del Excel del ERP a lineas_pedido (idempotente).';

    public function handle(ImportadorErp $importador): int
    {
        $ruta = (string) $this->argument('ruta');

        $this->info("Importando: {$ruta}");

        try {
            $resumen = $importador->importar($ruta);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $filas = [];
        foreach ($resumen as $hoja => $r) {
            $filas[] = [$hoja, $r['insertadas'], $r['actualizadas'], $r['desactivadas']];
        }

        $this->table(['Hoja', 'Insertadas', 'Actualizadas', 'Desactivadas'], $filas);
        $this->info('Importación completada.');

        return self::SUCCESS;
    }
}
