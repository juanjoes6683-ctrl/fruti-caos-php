-- ============================================================================
-- database/migracion_autenticacion.sql
-- Para quien YA importó la versión anterior de fruti_caos.sql (sin autenticación).
-- Agrega a la tabla jugadores las columnas de contraseña y rol. EJECUTAR UNA SOLA VEZ.
-- (Si estás instalando desde cero, NO hace falta: fruti_caos.sql ya las incluye.)
-- ============================================================================

-- Selecciona la base de datos del proyecto
USE fruti_caos;

-- Agrega las dos columnas nuevas después de "tag"
ALTER TABLE jugadores
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER tag,                          -- hash de la contraseña (NULL = sin acceso)
  ADD COLUMN rol ENUM('jugador','admin') NOT NULL DEFAULT 'jugador' AFTER password_hash;  -- permisos del jugador

-- Los jugadores que ya existían quedan sin contraseña (no pueden iniciar sesión).
-- Si querés usar uno de esos tags, borralo y volvé a registrarlo con contraseña:
-- DELETE FROM jugadores WHERE tag = 'MiTagViejo';
