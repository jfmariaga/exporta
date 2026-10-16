<?php

namespace App\Support;

/**
 * Consulta de sólo lectura contra SQL Server (`panalsrvdb` / db `panalsas`), que
 * federa a Oracle por el linked server `LOGGROPDN` vía `OPENQUERY`.
 *
 * Es la misma consulta "Pedidos PL" que usa el Power Query del archivo
 * `Q:\Consulta SQL Pedidos-Despachos.xlsx`, con dos filtros extra empujados a
 * Oracle: estado en (PL, PP) e ítem que no empieza por 7.
 *
 * Compañía '01', tipo de consecutivo '0542', clase de documento 'PEVE',
 * elaboración posterior a 2024-10-01.
 */
final class ConsultasErp
{
    /**
     * T-SQL a ejecutar sobre la conexión `erp`. Devuelve una fila por línea de
     * pedido de venta de exportación pendiente de liberar, con las columnas:
     * pedido, bodega, item, descripcion, grupoitem, fechaelaboracion,
     * unidadmedida, cantidadpedida, cantidaddespachada, fecharequerida,
     * fechadespacho, preciototalneto, nitcliente, nombrecliente, estado,
     * fechaultimomovimiento, usuario.
     */
    public static function pedidosPl(): string
    {
        return <<<'SQL'
SELECT *
FROM OPENQUERY(LOGGROPDN, '
SELECT
  in_pediddetal.pedconsecutivo AS pedido,
  in_pedidencab.peebodega AS bodega,
  in_pediddetal.pedcodiitem AS item,
  in_items.itedesccort AS descripcion,
  in_grupoitems.gitnombre AS grupoitem,
  in_pediddetal.pedfechelab AS fechaelaboracion,
  in_pediddetal.pedumv AS unidadmedida,
  in_pediddetal.pedcantpediump AS cantidadpedida,
  in_pediddetal.pedcantdespump AS cantidaddespachada,
  in_pediddetal.pedfechrequ AS fecharequerida,
  in_pediddetal.pedfechtempdesp AS fechadespacho,
  in_pediddetal.pedprectotaneto AS preciototalneto,
  in_pedidencab.peecliente AS nitcliente,
  a.tgenombcomp AS nombrecliente,
  in_pediddetal.eobcodigo AS estado,
  in_pediddetal.eobfecha AS fechaultimomovimiento,
  in_pediddetal.eobusuario AS usuario
FROM in_pedidencab
JOIN in_pediddetal
  ON in_pediddetal.pedcompania = in_pedidencab.peecompania
AND in_pediddetal.pedtipocons = in_pedidencab.peetipocons
AND in_pediddetal.pedconsecutivo = in_pedidencab.peeconsecutivo
JOIN in_items
  ON in_items.itecompania = in_pediddetal.pedcompania
AND in_items.itecodigo = in_pediddetal.pedcodiitem
JOIN un_terceclien
  ON un_terceclien.tclcompania = in_pediddetal.pedcompania
AND un_terceclien.tclcodigo = in_pediddetal.pedestablecimiento
AND un_terceclien.tcltercegener = in_pediddetal.pedcliente
LEFT JOIN in_grupoitems
  ON in_items.itegrupo = in_grupoitems.gitcodigo
AND in_items.itecompania = in_grupoitems.gitcompania
LEFT JOIN in_tipositems
  ON in_items.itetipoitem = in_tipositems.titcodigo
AND in_items.itecompania = in_tipositems.titcompania
LEFT JOIN in_itemsean
  ON in_pediddetal.pedcodiitem = in_itemsean.eancodiitem
AND in_pediddetal.pedcompania = in_itemsean.eancompania
AND NVL(in_pediddetal.pedatributo1, ''*'') = NVL(in_itemsean.eanvaloatri1, ''*'')
AND NVL(in_pediddetal.pedatributo2, ''*'') = NVL(in_itemsean.eanvaloatri2, ''*'')
AND NVL(in_pediddetal.pedatributo3, ''*'') = NVL(in_itemsean.eanvaloatri3, ''*'')
AND NVL(in_pediddetal.pedatributo4, ''*'') = NVL(in_itemsean.eanvaloatri4, ''*'')
AND in_itemsean.eanvigente = ''S''
LEFT JOIN un_tercegener a
  ON in_pedidencab.peecliente = a.tgecodigo
AND in_pedidencab.peecompania = a.tgecompania
LEFT JOIN un_tercegener b
  ON in_pedidencab.peevendedor = b.tgecodigo
AND in_pedidencab.peecompania = b.tgecompania
LEFT JOIN ca_localgeogr
  ON un_terceclien.tcllocaniv1 = ca_localgeogr.lgenivel1
AND un_terceclien.tcllocaniv2 = ca_localgeogr.lgenivel2
AND un_terceclien.tcllocaniv3 = ca_localgeogr.lgenivel3
AND un_terceclien.tcllocaniv4 = ca_localgeogr.lgenivel4
AND un_terceclien.tcllocaniv5 = ca_localgeogr.lgenivel5
AND un_terceclien.tclcompania = ca_localgeogr.lgecompania
WHERE
  in_pediddetal.pedtipocons = ''0542''
  AND in_pediddetal.pedfechelab > TO_DATE(''2024-10-01 23:59:59'', ''YYYY-MM-DD HH24:MI:SS'')
  AND in_pedidencab.peeclasdocu = ''PEVE''
  AND in_pediddetal.pedcompania = ''01''
  AND in_pediddetal.eobcodigo IN (''PL'', ''PP'')
  AND in_pediddetal.pedcodiitem NOT LIKE ''7%''
')
SQL;
    }
}
