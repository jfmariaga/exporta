<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Carga `documentacion/ACTIVIDAD POR DESTINO.xlsx` (hoja "LISTADO POR
 * DESTINO") a `actividades_destino`.
 *
 * Reconciliación por la clave compuesta `item`+`destino` (no reemplaza la
 * tabla completa):
 * - Ítem+destino en el Excel Y en la BD → se actualiza con los datos del
 *   Excel (categoria, actividad).
 * - Ítem+destino en el Excel pero no en la BD → se crea.
 * - Ítem+destino en la BD pero no en el Excel → se deja tal cual, no se
 *   borra.
 */
class ActividadDestinoExcelSeeder extends Seeder
{
    private const MAPA = [
        'item' => 'item',
        'destino' => 'destino',
        'categoria' => 'categoria',
        'actividad' => 'actividad',
    ];

    public function run(): void
    {
        $ruta = base_path('documentacion/ACTIVIDAD POR DESTINO.xlsx');

        if (! is_file($ruta)) {
            throw new RuntimeException("No se encontró el archivo: {$ruta}");
        }

        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $libro = $reader->load($ruta);

        $hoja = $libro->getSheetByName('LISTADO POR DESTINO') ?? $libro->getSheet(0);
        $filas = $hoja->toArray(null, true, false, false);

        [$indices, $faltantes] = $this->resolverColumnas($filas, self::MAPA);
        if ($faltantes !== []) {
            throw new RuntimeException('Hoja LISTADO POR DESTINO: faltan columnas '.implode(', ', $faltantes));
        }

        $registros = [];
        foreach (array_slice($filas, 1) as $fila) {
            $registro = $this->mapearFila($fila, $indices);
            if ($registro === null || ($registro['item'] ?? '') === '' || ($registro['destino'] ?? '') === '') {
                continue;
            }

            $registro['updated_at'] = now();
            $registro['created_at'] = now();

            // Si hay combinaciones repetidas en el Excel, se queda con la última fila.
            $registros[$registro['item'].'|'.$registro['destino']] = $registro;
        }

        if ($registros === []) {
            $this->command?->warn('ActividadDestinoExcelSeeder: el Excel no trajo filas válidas, no se tocó la BD.');

            return;
        }

        $antes = DB::table('actividades_destino')->count();

        $columnasActualizables = array_values(array_diff(
            array_keys(reset($registros)),
            ['item', 'destino', 'created_at'],
        ));

        foreach (array_chunk(array_values($registros), 500) as $lote) {
            DB::table('actividades_destino')->upsert($lote, ['item', 'destino'], $columnasActualizables);
        }

        $despues = DB::table('actividades_destino')->count();
        $insertadas = max(0, $despues - $antes);
        $actualizadas = count($registros) - $insertadas;
        $sinTocar = max(0, $antes - $actualizadas);

        $this->command?->info(sprintf(
            'ActividadDestinoExcelSeeder: %d fila(s) del Excel procesadas (%d nuevas, %d actualizadas). %d fila(s) existentes en BD sin tocar por no estar en el Excel.',
            count($registros),
            $insertadas,
            $actualizadas,
            $sinTocar,
        ));
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @param  array<string,string>  $mapa
     * @return array{0: array<string,int>, 1: list<string>}
     */
    private function resolverColumnas(array $filas, array $mapa): array
    {
        $encabezados = $filas[0] ?? [];
        $porNombre = [];
        foreach ($encabezados as $i => $texto) {
            $porNombre[$this->norm((string) $texto)] = $i;
        }

        $indices = [];
        $faltantes = [];
        foreach ($mapa as $encabezado => $columna) {
            $encontrado = $porNombre[$encabezado] ?? null;
            if ($encontrado === null) {
                foreach ($porNombre as $nombre => $idx) {
                    if (str_contains($nombre, $encabezado)) {
                        $encontrado = $idx;
                        break;
                    }
                }
            }
            if ($encontrado === null) {
                $faltantes[] = $encabezado;
            } else {
                $indices[$columna] = $encontrado;
            }
        }

        return [$indices, $faltantes];
    }

    /**
     * @param  list<mixed>  $fila
     * @param  array<string,int>  $indices
     * @return array<string,mixed>|null
     */
    private function mapearFila(array $fila, array $indices): ?array
    {
        $r = [];
        $vacia = true;
        foreach ($indices as $columna => $idx) {
            $valor = $fila[$idx] ?? null;
            if (is_string($valor)) {
                $valor = trim($valor);
            }
            if ($valor !== null && $valor !== '') {
                $vacia = false;
            }
            $r[$columna] = $valor;
        }

        if ($vacia) {
            return null;
        }

        if (isset($r['item'])) {
            $r['item'] = $this->aCodigoItem($r['item']);
        }

        return $r;
    }

    private function norm(string $texto): string
    {
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n']);

        return preg_replace('/[^a-z0-9]/', '', strtolower($texto)) ?? '';
    }

    private function aCodigoItem(mixed $valor): string
    {
        if (is_int($valor) || (is_float($valor) && floor($valor) === $valor)) {
            return str_pad((string) (int) $valor, 6, '0', STR_PAD_LEFT);
        }
        $texto = trim((string) $valor);

        return ctype_digit($texto) && strlen($texto) < 6
            ? str_pad($texto, 6, '0', STR_PAD_LEFT)
            : $texto;
    }
}
