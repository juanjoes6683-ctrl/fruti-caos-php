-- ============================================================================
-- database/fruti_caos.sql
-- Esquema y datos de prueba de FRUTI-CAOS (MySQL / MariaDB).
-- Importar en phpMyAdmin (pestaña "Importar"). Se puede ejecutar varias veces sin duplicar datos.
-- ============================================================================

-- Crea la base de datos si todavía no existe (utf8mb4 = acentos y emojis)
CREATE DATABASE IF NOT EXISTS fruti_caos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Selecciona la base para que las siguientes sentencias trabajen sobre ella
USE fruti_caos;

-- ---------------------------------------------------------------------------
-- TABLA 1: jugadores (un registro por tag)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jugadores (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,  -- identificador único autogenerado
  tag        VARCHAR(20)  NOT NULL,                             -- nombre elegido por el jugador (es su usuario)
  password_hash VARCHAR(255) NULL,                              -- contraseña guardada como hash (NULL = jugador de ejemplo, sin acceso)
  rol        ENUM('jugador','admin') NOT NULL DEFAULT 'jugador', -- permisos: "admin" se asigna solo con SQL (ver abajo)
  creado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- fecha de registro
  UNIQUE KEY uq_jugadores_tag (tag)                             -- no puede haber dos jugadores con el mismo tag
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- TABLA 2: partidas (cada sesión de juego; pertenece a un jugador)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS partidas (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,           -- identificador único autogenerado
  jugador_id   INT UNSIGNED NOT NULL,                                      -- jugador dueño de la partida
  puntaje      INT UNSIGNED NOT NULL,                                      -- puntos obtenidos
  nivel_jugo   TINYINT UNSIGNED NOT NULL DEFAULT 0,                        -- nivel de jugo, de 0 a 5
  resultado    ENUM('jugo','caja_llena') NOT NULL DEFAULT 'jugo',          -- cómo terminó la partida
  creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,               -- fecha y hora de la partida
  eliminado_en TIMESTAMP NULL DEFAULT NULL,                                -- borrado lógico: NULL = activa, con fecha = eliminada
  INDEX idx_partidas_puntaje (puntaje),                                    -- acelera el ranking
  INDEX idx_partidas_jugador (jugador_id),                                 -- acelera buscar las partidas de un jugador
  CONSTRAINT fk_partidas_jugador                                           -- relación con la tabla jugadores
    FOREIGN KEY (jugador_id) REFERENCES jugadores (id)
    ON DELETE CASCADE                                                      -- si se borra el jugador, se borran sus partidas
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- DATOS INICIALES DE PRUEBA
-- ---------------------------------------------------------------------------

-- Jugadores de ejemplo (INSERT IGNORE salta los que ya existan por el tag único).
-- No tienen contraseña (password_hash NULL), así que nadie puede iniciar sesión con ellos: solo sirven para ver el ranking.
INSERT IGNORE INTO jugadores (tag) VALUES
  ('FrutaNinja'),
  ('MangoLoco'),
  ('SandiaKing');

-- Partidas de ejemplo: solo se cargan si la tabla partidas está vacía (evita duplicados al reimportar)
INSERT INTO partidas (jugador_id, puntaje, nivel_jugo, resultado)
SELECT * FROM (
  SELECT (SELECT id FROM jugadores WHERE tag = 'FrutaNinja') AS jugador_id, 9800 AS puntaje, 4 AS nivel_jugo, 'jugo' AS resultado
  UNION ALL SELECT (SELECT id FROM jugadores WHERE tag = 'FrutaNinja'), 4100, 2, 'jugo'        -- 2.ª partida del mismo jugador: el ranking solo mostrará la mejor
  UNION ALL SELECT (SELECT id FROM jugadores WHERE tag = 'MangoLoco'), 6400, 3, 'jugo'
  UNION ALL SELECT (SELECT id FROM jugadores WHERE tag = 'SandiaKing'), 3200, 2, 'caja_llena'
) AS ejemplos
WHERE NOT EXISTS (SELECT 1 FROM partidas);

-- ---------------------------------------------------------------------------
-- ADMINISTRADOR: no se crea ninguno acá para no dejar contraseñas en el código.
-- 1) Registrá una cuenta normal desde el juego o con POST /api/jugadores.
-- 2) Ascendela a administrador con esta sentencia (cambiá TuTag por el tag registrado):
-- UPDATE jugadores SET rol = 'admin' WHERE tag = 'TuTag';
-- ---------------------------------------------------------------------------

-- Si todavía tenías la tabla "puntajes" de la versión anterior, ya no se usa. Para borrarla:
-- DROP TABLE IF EXISTS puntajes;

-- Para borrar los datos de ejemplo cuando ya no los quieras (las partidas se borran en cascada):
-- DELETE FROM jugadores WHERE tag IN ('FrutaNinja', 'MangoLoco', 'SandiaKing');
