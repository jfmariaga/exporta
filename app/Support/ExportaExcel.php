<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta una tabla a .xlsx con PhpSpreadsheet directo (misma convención que
 * `ImportadorErp` para leer Excel), sin depender de maatwebsite/excel.
 */
trait ExportaExcel
{
    /**
     * @param  list<string>  $encabezados
     * @param  iterable<int, iterable<int, mixed>>  $filas
     */
    protected function descargarExcel(string $nombreArchivo, array $encabezados, iterable $filas): StreamedResponse
    {
        $hoja = new Spreadsheet;
        $sheet = $hoja->getActiveSheet();
        $sheet->fromArray($encabezados, null, 'A1');

        $i = 2;
        foreach ($filas as $fila) {
            $sheet->fromArray(array_values((array) $fila), null, 'A'.$i);
            $i++;
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($hoja);

        return response()->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            },
            $nombreArchivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
