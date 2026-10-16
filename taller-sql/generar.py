import json, datetime
D="C:/Users/atecnologia2/Downloads/"
L=lambda f: json.load(open(D+f,encoding='utf-8'))
jug,pai,pos=L('18-1 jugadores.json'),L('18-2 paises.json'),L('18-3 Posiciones_Futbol.json')
q=lambda s:"'"+str(s).replace("\\","\\\\").replace("'","''")+"'"
o=["""-- ============================================================
-- TALLER SQL · Analítica de Datos y BI  (MySQL 8 / Workbench)
-- Tema: FIFA Talent Analytics (299 jugadores)
-- Valor_eur y Salario_eur están en millones de euros.
-- ============================================================
DROP DATABASE IF EXISTS fifa;
CREATE DATABASE fifa CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE fifa;

CREATE TABLE paises (
  Nacionalidad CHAR(3) PRIMARY KEY,
  Pais VARCHAR(60) NOT NULL
);
CREATE TABLE posiciones (
  posicion VARCHAR(4) PRIMARY KEY,
  posicion_ingles VARCHAR(60) NOT NULL,
  posicion_espanol VARCHAR(60) NOT NULL
);
CREATE TABLE jugadores (
  idJugador INT PRIMARY KEY,
  Nombre VARCHAR(60), Apellidos VARCHAR(60),
  Edad INT, FechaNacimiento DATE,
  Altura_cm INT, Peso_kg INT,
  Nacionalidad CHAR(3), Club VARCHAR(60),
  Valor_eur DECIMAL(8,2), Salario_eur DECIMAL(8,2),
  Posicion VARCHAR(4),
  FOREIGN KEY (Nacionalidad) REFERENCES paises(Nacionalidad),
  FOREIGN KEY (Posicion) REFERENCES posiciones(posicion)
);
"""]
o.append("INSERT INTO paises VALUES\n"+",\n".join(f"({q(p['Nacionalidad'])},{q(p['Pais'])})" for p in pai)+";\n")
o.append("INSERT INTO posiciones VALUES\n"+",\n".join(f"({q(p['posicion'])},{q(p['posicion_ingles'])},{q(p['posicion_espanol'])})" for p in pos)+";\n")
def f(d): return datetime.datetime.strptime(d,"%d/%m/%Y").strftime("%Y-%m-%d")
o.append("INSERT INTO jugadores VALUES\n"+",\n".join(
 f"({j['idJugador']},{q(j['Nombre'])},{q(j['Apellidos'])},{j['Edad']},{q(f(j['FechaNacimiento']))},{j['Altura_cm']},{j['Peso_kg']},{q(j['Nacionalidad'])},{q(j['Club'])},{j['Valor_eur']},{j['Salario_eur']},{q(j['Posicion'])})" for j in jug)+";\n")
open('taller-sql/01_esquema_datos.sql','w',encoding='utf-8').write("SET NAMES utf8mb4;\n"+"\n".join(o))
