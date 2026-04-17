-- Migración: PIN de recuperación (4 dígitos)
--
-- Objetivo:
-- - Permitir que cada usuario tenga un PIN de recuperación (hash) para restablecer contraseña
--   sin depender de correo/SMS/WhatsApp.
-- - Agregar control básico de intentos para evitar fuerza bruta.
--
-- Nota: ejecuta esto sobre la base de datos donde esté la tabla `usuarios`.
--
-- Este script es "seguro" para ejecutar más de una vez: antes de agregar cada columna,
-- verifica si ya existe en INFORMATION_SCHEMA.

SET @db := DATABASE();

-- recovery_pin_hash
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'recovery_pin_hash'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `recovery_pin_hash` VARCHAR(255) NULL AFTER `password`',
  'SELECT "OK: recovery_pin_hash ya existe"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- recovery_pin_set_at
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'recovery_pin_set_at'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `recovery_pin_set_at` DATETIME NULL AFTER `recovery_pin_hash`',
  'SELECT "OK: recovery_pin_set_at ya existe"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pin_failed_attempts
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'pin_failed_attempts'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `pin_failed_attempts` INT NOT NULL DEFAULT 0 AFTER `recovery_pin_set_at`',
  'SELECT "OK: pin_failed_attempts ya existe"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pin_locked_until
SET @exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'pin_locked_until'
);
SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `pin_locked_until` DATETIME NULL AFTER `pin_failed_attempts`',
  'SELECT "OK: pin_locked_until ya existe"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

