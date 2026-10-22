<?php

namespace App\Services;

use App\Services\Erp\ConsultaDirectaErp;
use App\Services\Erp\LineasPedidoSync;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Fuente ERP "importar Excel": carga la hoja PedidosVenta/"Pedidos Liberar"
 * del archivo a `lineas_pedido`. Idempotente (upsert por clave natural) y
 * transaccional. Nunca toca `seguimientos` ni `seguimiento_pedidos`.
 *
 * `clientes` y `parametros_item` son maestros manuales que se gestionan
 * desde la UI (admin/clientes, admin/parametros-item) y ya no se tocan aquí.
 *
 * La escritura de `lineas_pedido` la delega en {@see LineasPedidoSync}, que
 * comparte con la fuente "consulta directa" ({@see ConsultaDirectaErp}).
 */
class ImportadorErp
{
    public function __construct(private LineasPedidoSync $sync) {}

    /** Encabezado de hoja (normalizado) => columna de BD, por tabla. */
    private const MAPA_LINEAS = [
        'pedido' => 'pedido',
        'bodega' => 'bodega',
        'item' => 'item',
        'descripcion' => 'descripcion',
        'grupoitem' => 'grupo_item',
        'fechaelaboracion' => 'fecha_elaboracion',
        'unidadmedida' => 'unidad_medida',
        'cantidadpedida' => 'cantidad_pedida',
        'cantidaddespachada' => 'cantidad_despachada',
        'fecharequerida' => 'fecha_requerida',
        'fechadespacho' => 'fecha_despacho',
        'preciototalneto' => 'precio_total_neto',
        'nitcliente' => 'nit_cliente',
        'nombrecliente' => 'nombre_cliente',
        'estado' => 'estado',
        'fechaultimomovimiento' => 'fecha_ultimo_movimiento',
        'usuario' => 'usuario',
    ];

    private const FECHAS_LINEAS = [
        'fecha_elaboracion', 'fecha_requerida', 'fecha_despacho', 'fecha_ultimo_movimiento',
    ];

    /**
     * @return array<string, array{insertadas:int, actualizadas:int, desactivadas:int}>
     */
    public function importar(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new RuntimeException("No se encontró el archivo: {$ruta}");
        }

        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $libro = $reader->load($ruta);

        $hojas = [];
        foreach ($libro->getSheetNames() as $nombre) {
            $hojas[$this->norm($nombre)] = $libro->getSheetByName($nombre)->toArray(null, true, false, false);
        }

        $hojaLineas = $hojas['pedidosventa'] ?? $hojas['pedidosliberar'] ?? null;

        if ($hojaLineas === null) {
            throw new RuntimeException("Falta la hoja 'PedidosVenta' o 'Pedidos Liberar'.");
        }

        return DB::transaction(function () use ($hojaLineas) {
            return [
                'PedidosVenta' => $this->cargarLineas($hojaLineas),
            ];
        });
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @return array{insertadas:int, actualizadas:int, desactivadas:int}
     */
    private function cargarLineas(array $filas): array
    {
        [$indices, $faltantes] = $this->resolverColumnas($filas, self::MAPA_LINEAS);
        if ($faltantes !== []) {
            throw new RuntimeException('Hoja de líneas: faltan columnas '.implode(', ', $faltantes));
        }

        $registros = [];
        foreach (array_slice($filas, 1) as $fila) {
            $r = $this->mapearFila($fila, $indices, 'lineas_pedido');
            if ($r === null || ($r['pedido'] ?? '') === '' || ($r['item'] ?? '') === '') {
                continue;
            }
            foreach (self::FECHAS_LINEAS as $c) {
                $r[$c] = $this->aFecha($r[$c] ?? null);
            }
            $r['cantidad_pedida'] = $this->aNumero($r['cantidad_pedida'] ?? 0) ?? 0;
            $r['cantidad_despachada'] = $this->aNumero($r['cantidad_despachada'] ?? null);
            $r['precio_total_neto'] = $this->aNumero($r['precio_total_neto'] ?? null);

            $registros[$r['pedido'].'|'.$r['item']] = $r;
        }

        return $this->sync->sincronizar($registros);
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
            // coincidencia por "contiene" para tolerar variantes ("Factor kg", "Cajaspor nivel", …)
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
    private function mapearFila(array $fila, array $indices, string $tabla): ?array
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
        if (isset($r['nit_cliente'])) {
            $r['nit_cliente'] = $this->aTextoNit($r['nit_cliente']);
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

    private function aTextoNit(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) === $valor) {
            return (string) (int) $valor;
        }

        return trim((string) $valor);
    }

    private function aNumero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        $limpio = str_replace([' ', ','], ['', '.'], (string) $valor);

        return is_numeric($limpio) ? (float) $limpio : null;
    }

    private function aFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d');
        }
        try {
            return Carbon::parse((string) $valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
