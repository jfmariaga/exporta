<?php

namespace App\Support;

/**
 * Consulta de sólo lectura contra Postgres (SyncBox, db `syncboxi40`).
 *
 * Totaliza el inventario de exportación por bodega/ubicación/código/lote,
 * a partir de los movimientos registrados (`inventory.log_movement_product`),
 * quedándose sólo con las filas marcadas para exportación.
 */
final class ConsultasInventario
{
    /**
     * SQL a ejecutar sobre la conexión `inventario`. Devuelve una fila por
     * (bodega, ubicación, código, lote, bloqueado) con columnas: warehouse,
     * ubication, code, description, lote, product_id, amount, is_blocked,
     * created, comments.
     */
    public static function existenciasExportacion(): string
    {
        return <<<'SQL'
WITH _infoubk AS (
			SELECT
				COALESCE(lmp.parent_id, lmp.log_movement_product_id) AS log_movement_product_id,
				w.code AS warehouse,
				u.code AS ubication,
				p.code,
				p.description,
				lmp.product_id,
				SUM(
					CASE
						WHEN lmp.is_income THEN 1
						ELSE -1
					END * lmp.amount
				) AS amount,
				lmp.lote,
				lmp."module",
				MIN(lmp.created) :: date AS created,
				is_blocked,
				p.details,
				lmp."comments"
			FROM
				inventory.log_movement_product lmp
				INNER JOIN inventory.ubications u ON lmp.ubication_id = u.ubications_id
				INNER JOIN master.products p ON p.product_id = lmp.product_id
				INNER JOIN inventory.warehouse w ON w.warehouse_id = u.warehouse_id
			GROUP BY
				w.code,
				u.code,
				is_blocked,
				p.description,
				lmp.product_id,
				lmp.lote,
				lmp."module",
				COALESCE(lmp.parent_id, lmp.log_movement_product_id),
				p.code,
				p.details,
				lmp."comments"
		)
				SELECT
					warehouse,
					ubication,
					code,
					description,
					lote,
					product_id,
					SUM(amount) AS amount,
					is_blocked,
					MIN(created) AS created,
					STRING_AGG(DISTINCT comments, ', ') FILTER (
						WHERE
							comments IS NOT NULL
							AND comments <> ''
							and comments in ('Export.')
					) AS comments
				FROM
					_infoubk
				GROUP BY
					warehouse,
					ubication,
					code,
					description,
					lote,
					product_id,
					is_blocked
				HAVING
					SUM(amount) > 0
					AND BOOL_OR(comments in ('Export.','Exportacion'))
				ORDER BY
					MIN(created);
SQL;
    }
}
