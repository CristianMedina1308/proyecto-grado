-- Migración: PIN de recuperación (4 dígitos)
--
-- Objetivo:
-- - Permitir que cada usuario tenga un PIN de recuperación (hash) para restablecer contraseña
--   sin depender de correo/SMS/WhatsApp.
-- - Agregar control básico de intentos para evitar fuerza bruta.
--
-- Nota: ejecuta esto sobre la base de datos donde esté la tabla `usuarios`.

ALTER TABLE `usuarios`
  ADD COLUMN `recovery_pin_hash` VARCHAR(255) NULL AFTER `password`,
  ADD COLUMN `recovery_pin_set_at` DATETIME NULL AFTER `recovery_pin_hash`,
  ADD COLUMN `pin_failed_attempts` INT NOT NULL DEFAULT 0 AFTER `recovery_pin_set_at`,
  ADD COLUMN `pin_locked_until` DATETIME NULL AFTER `pin_failed_attempts`;

