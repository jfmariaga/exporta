-- ============================================================
-- TALLER SQL · Ejercicios con dificultad progresiva
-- Ejecutar primero 01_esquema_datos.sql
-- ============================================================
USE fifa;

-- ---------- NIVEL 1 · DISTINCT, COUNT, WHERE ----------
-- 1. ¿Cuántos jugadores hay en total?
SELECT COUNT(*) AS total_jugadores FROM jugadores;

-- 2. ¿Cuántos clubes distintos existen?
SELECT COUNT(DISTINCT Club) AS clubes FROM jugadores;

-- 3. Lista de posiciones distintas usadas, ordenadas.
SELECT DISTINCT Posicion FROM jugadores ORDER BY Posicion;

-- 4. Jugadores con valor mayor a 100 millones.
SELECT Nombre, Apellidos, Club, Valor_eur
FROM jugadores
WHERE Valor_eur > 100
ORDER BY Valor_eur DESC;

-- ---------- NIVEL 2 · SUM, IN, ORDER BY ----------
-- 5. Valor total (millones) de todos los jugadores.
SELECT SUM(Valor_eur) AS valor_total FROM jugadores;

-- 6. Jugadores brasileños, argentinos o uruguayos (IN), del más joven al mayor.
SELECT Nombre, Apellidos, Nacionalidad, Edad
FROM jugadores
WHERE Nacionalidad IN ('BRA','ARG','URY')
ORDER BY Edad, Apellidos;

-- 7. Porteros y defensas centrales mayores de 30 años.
SELECT Nombre, Apellidos, Posicion, Edad
FROM jugadores
WHERE Posicion IN ('GK','CB') AND Edad > 30
ORDER BY Edad DESC;

-- ---------- NIVEL 3 · GROUP BY, HAVING ----------
-- 8. Cantidad de jugadores y valor total por club.
SELECT Club, COUNT(*) AS jugadores, SUM(Valor_eur) AS valor_total
FROM jugadores
GROUP BY Club
ORDER BY valor_total DESC;

-- 9. Clubes con al menos 5 jugadores (HAVING).
SELECT Club, COUNT(*) AS jugadores
FROM jugadores
GROUP BY Club
HAVING COUNT(*) >= 5
ORDER BY jugadores DESC, Club;

-- 10. Posiciones cuyo valor promedio supera 80 millones.
SELECT Posicion, COUNT(*) AS jugadores, ROUND(AVG(Valor_eur),1) AS valor_prom
FROM jugadores
GROUP BY Posicion
HAVING AVG(Valor_eur) > 80
ORDER BY valor_prom DESC;

-- ---------- NIVEL 4 · INNER JOIN ----------
-- 11. Jugadores con el nombre del país (no el código).
SELECT CONCAT(j.Nombre,' ',j.Apellidos) AS jugador, p.Pais, j.Club
FROM jugadores j
INNER JOIN paises p ON p.Nacionalidad = j.Nacionalidad
ORDER BY p.Pais, jugador;

-- 12. Top 10 países por valor total de sus jugadores.
SELECT p.Pais, COUNT(*) AS jugadores, SUM(j.Valor_eur) AS valor_total
FROM jugadores j
INNER JOIN paises p ON p.Nacionalidad = j.Nacionalidad
GROUP BY p.Pais
ORDER BY valor_total DESC
LIMIT 10;

-- 13. Cantidad de jugadores por posición en español, con 3 tablas unidas.
SELECT ps.posicion_espanol, COUNT(*) AS jugadores, ROUND(AVG(j.Altura_cm),1) AS altura_prom
FROM jugadores j
INNER JOIN posiciones ps ON ps.posicion = j.Posicion
INNER JOIN paises pa ON pa.Nacionalidad = j.Nacionalidad
GROUP BY ps.posicion_espanol
HAVING COUNT(*) >= 5
ORDER BY jugadores DESC;

-- ---------- NIVEL 5 · DENSE_RANK() ----------
-- 14. Ranking de jugadores por valor (los empates comparten puesto, sin saltos).
SELECT DENSE_RANK() OVER (ORDER BY Valor_eur DESC) AS puesto,
       CONCAT(Nombre,' ',Apellidos) AS jugador, Club, Valor_eur
FROM jugadores
ORDER BY puesto, jugador
LIMIT 15;

-- 15. Los 3 jugadores más valiosos de CADA posición.
SELECT * FROM (
  SELECT ps.posicion_espanol,
         CONCAT(j.Nombre,' ',j.Apellidos) AS jugador, j.Valor_eur,
         DENSE_RANK() OVER (PARTITION BY j.Posicion ORDER BY j.Valor_eur DESC) AS puesto
  FROM jugadores j
  INNER JOIN posiciones ps ON ps.posicion = j.Posicion
) t
WHERE puesto <= 3
ORDER BY posicion_espanol, puesto, jugador;

-- 16. (Reto) Ranking de países por valor total y el jugador estrella de cada uno de los 5 primeros.
WITH pais_tot AS (
  SELECT p.Nacionalidad, p.Pais, SUM(j.Valor_eur) AS valor_total,
         DENSE_RANK() OVER (ORDER BY SUM(j.Valor_eur) DESC) AS puesto_pais
  FROM jugadores j
  INNER JOIN paises p ON p.Nacionalidad = j.Nacionalidad
  GROUP BY p.Nacionalidad, p.Pais
),
estrella AS (
  SELECT Nacionalidad, CONCAT(Nombre,' ',Apellidos) AS jugador, Valor_eur,
         DENSE_RANK() OVER (PARTITION BY Nacionalidad ORDER BY Valor_eur DESC) AS r
  FROM jugadores
)
SELECT pt.puesto_pais, pt.Pais, pt.valor_total, e.jugador AS estrella, e.Valor_eur
FROM pais_tot pt
INNER JOIN estrella e ON e.Nacionalidad = pt.Nacionalidad AND e.r = 1
WHERE pt.puesto_pais <= 5
ORDER BY pt.puesto_pais, estrella;
