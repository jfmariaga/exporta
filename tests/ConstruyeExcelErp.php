<?php

namespace Tests;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait ConstruyeExcelErp
{
    /**
     * Genera un .xlsx del ERP con las 3 hojas y lo devuelve como ruta temporal.
     *
     * Incluye a propósito: 1 ítem 7x (se debe ignorar), 1 ítem sin parámetro,
     * 1 NIT sin cliente, estados PL/PP/CO, 2 pedidos por liberar.
     *
     * @param  array{lineas?: list<array<int,mixed>>, clientes?: list<array<int,mixed>>, parametros?: list<array<int,mixed>>}  $overrides
     */
    protected function excelErp(array $overrides = []): string
    {
        $lineas = $overrides['lineas'] ?? [
            ['PEDIDO', 'BODEGA', 'ITEM', 'DESCRIPCION', 'GRUPOITEM', 'FECHAELABORACION', 'UNIDADMEDIDA', 'CANTIDADPEDIDA', 'CANTIDADDESPACHADA', 'FECHAREQUERIDA', 'FECHADESPACHO', 'PRECIOTOTALNETO', 'NITCLIENTE', 'NOMBRECLIENTE', 'ESTADO', 'FECHAULTIMOMOVIMIENTO', 'USUARIO'],
            ['179', '004', '001131', 'ESENC COCO LEVAPAN 24X500cm3 P', 'ESENCIAS - VIDRIO', '2026-08-04', 'UN', 2400, 0, '2026-08-05', '2026-08-04', 5568, 'B66061078', 'DISTRIBAR DISTRIBUCION HOSTELERIA SL', 'PL', '2026-08-04', 'SBERNAL'],
            ['179', '004', '001200', 'PRODUCTO SIN FACTOR', 'VARIOS', '2026-08-06', 'UN', 100, 0, '2026-08-07', '2026-08-06', 200, 'B66061078', 'DISTRIBAR DISTRIBUCION HOSTELERIA SL', 'PL', '2026-08-06', 'SBERNAL'],
            ['179', '004', '700010', 'ITEM QUE EMPIEZA POR 7', 'RECUPERACIONES', '2026-08-04', 'UN', 5000, 0, '2026-08-05', '2026-08-04', 10, 'B66061078', 'DISTRIBAR DISTRIBUCION HOSTELERIA SL', 'PL', '2026-08-04', 'SBERNAL'],
            ['180', '004', '030848', 'MAYONESDELMONTEDP24x200g', 'DOY PACK', '2026-08-10', 'UN', 1000, 0, '2026-08-12', '2026-08-10', 3000, '99999999', 'CLIENTE SIN FICHA', 'PP', '2026-08-10', 'SBERNAL'],
            ['181', '004', '001131', 'ESENC COCO LEVAPAN 24X500cm3 P', 'ESENCIAS - VIDRIO', '2026-07-01', 'UN', 500, 500, '2026-07-02', '2026-07-01', 1200, 'B66061078', 'DISTRIBAR DISTRIBUCION HOSTELERIA SL', 'CO', '2026-07-10', 'CPROD1'],
        ];

        $clientes = $overrides['clientes'] ?? [
            ['NitCliente', 'Cliente', 'Destino'],
            ['B66061078', 'DISTRIBAR DISTRIBUCION HOSTELERIA SL', 'ESPAÑA'],
            ['77749262', 'IMPORTADORA Y DISTRIBUIDORA COLOMBIA PUES SPA', 'CHILE'],
        ];

        $parametros = $overrides['parametros'] ?? [
            ['Item', 'Descripcion', 'Factor kg', 'Categoria', 'Embalaje', 'Volumen', '#Tendidos', 'Cajas Pallet', 'Altura Caja', 'Cajaspor nivel'],
            ['001131', 'ESENC COCO LEVAPAN 24X500cm3 P', 0.549125, 'ESENCIAS Y VINAGRE', 24, 25137, 8, 48, 21, 6],
            ['030848', 'MAYONESDELMONTEDP24x200g', 0.2225, 'DOY PACK', 24, 11511.5, 13, 130, 14.3, 10],
        ];

        $libro = new Spreadsheet;
        $libro->removeSheetByIndex(0);

        foreach (['PedidosVenta' => $lineas, 'Clientes' => $clientes, 'Parametros' => $parametros] as $nombre => $filas) {
            $hoja = $libro->createSheet();
            $hoja->setTitle($nombre);
            $hoja->fromArray($filas, null, 'A1');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'erp_').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return $ruta;
    }
}
