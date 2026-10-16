<?php

namespace App\Services\Erp;

use App\Services\ImportadorErp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Escritura idempotente de la tabla espejo `lineas_pedido`.
 *
 * Reutilizado por las dos fuentes del ERP (constitución, Principio II — el ERP
 * es de sólo lectura):
 *  - {@see ImportadorErp}       importación desde el Excel.
 *  - {@see ConsultaDirectaErp} consulta directa a SQL Server.
 *
 * Estrategia: todas las filas se marcan inactivas y el upsert por `(pedido,item)`
 * reactiva las que vienen en el origen; las que ya no vienen quedan `activo=false`
 * (histórico, no se borran). Nunca toca `seguimientos` ni `seguimiento_pedidos`.
 */
class LineasPedidoSync
{
    /** Columnas de negocio que espera cada registro (sin metadatos). */
    private const COLUMNAS = [
        'pedido', 'item', 'bodega', 'descripcion', 'grupo_item', 'fecha_elaboracion',
        'unidad_medida', 'cantidad_pedida', 'cantidad_despachada', 'fecha_requerida',
        'fecha_despacho', 'precio_total_neto', 'nit_cliente', 'nombre_cliente', 'estado',
        'fecha_ultimo_movimiento', 'usuario',
    ];

    /**
     * @param  iterable<array<string,mixed>>  $registros  filas ya normalizadas (columnas de negocio)
     * @return array{insertadas:int, actualizadas:int, desactivadas:int}
     */
    public function sincronizar(iterable $registros): array
    {
        $filas = [];
        foreach ($registros as $r) {
            if (($r['pedido'] ?? '') === '' || ($r['item'] ?? '') === '') {
                continue;
            }
            $fila = [];
            foreach (self::COLUMNAS as $col) {
                $fila[$col] = $r[$col] ?? null;
            }
            $fila['cantidad_pedida'] ??= 0;
            $fila['activo'] = true;
            $fila['importado_en'] = now();
            $fila['created_at'] = now();
            $fila['updated_at'] = now();
            $filas[$fila['pedido'].'|'.$fila['item']] = $fila;
        }

        $filas = array_values($filas);

        if ($filas === []) {
            throw new RuntimeException('El origen del ERP no devolvió líneas; se aborta para no vaciar lineas_pedido.');
        }

        return DB::transaction(function () use ($filas) {
            $antes = DB::table('lineas_pedido')->count();
            $activasAntes = DB::table('lineas_pedido')->where('activo', true)->count();

            DB::table('lineas_pedido')->update(['activo' => false, 'updated_at' => now()]);

            foreach (array_chunk($filas, 500) as $lote) {
                DB::table('lineas_pedido')->upsert(
                    $lote,
                    ['pedido', 'item'],
                    ['bodega', 'descripcion', 'grupo_item', 'fecha_elaboracion', 'unidad_medida',
                        'cantidad_pedida', 'cantidad_despachada', 'fecha_requerida', 'fecha_despacho',
                        'precio_total_neto', 'nit_cliente', 'nombre_cliente', 'estado',
                        'fecha_ultimo_movimiento', 'usuario', 'activo', 'importado_en', 'updated_at'],
                );
            }

            $despues = DB::table('lineas_pedido')->count();
            $activasDespues = DB::table('lineas_pedido')->where('activo', true)->count();
            $insertadas = max(0, $despues - $antes);

            return [
                'insertadas' => $insertadas,
                'actualizadas' => count($filas) - $insertadas,
                'desactivadas' => max(0, $activasAntes + $insertadas - $activasDespues),
            ];
        });
    }

    // --- Normalizadores compartidos ------------------------------------------------

    /** Código de ítem como texto de 6 dígitos (preserva ceros a la izquierda). */
    public static function codigoItem(mixed $valor): string
    {
        if (is_int($valor) || (is_float($valor) && floor($valor) === $valor)) {
            return str_pad((string) (int) $valor, 6, '0', STR_PAD_LEFT);
        }
        $texto = trim((string) $valor);

        return ctype_digit($texto) && strlen($texto) < 6
            ? str_pad($texto, 6, '0', STR_PAD_LEFT)
            : $texto;
    }

    /** NIT como texto exacto (sin notación científica). */
    public static function textoNit(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) === $valor) {
            return (string) (int) $valor;
        }

        return trim((string) $valor);
    }

    public static function aNumero(mixed $valor): ?float
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

    public static function aFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor) && ! $valor instanceof \DateTimeInterface) {
            return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d');
        }
        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
