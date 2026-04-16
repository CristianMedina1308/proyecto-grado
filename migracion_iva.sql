-- Migracion IVA 19% (productos) para pedidos
-- Agrega columnas para persistir la tasa/monto de IVA sin romper pedidos existentes.

START TRANSACTION;

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS iva_rate DECIMAL(6,4) NULL DEFAULT NULL AFTER subtotal_productos,
  ADD COLUMN IF NOT EXISTS iva_monto DECIMAL(10,2) NULL DEFAULT NULL AFTER iva_rate;

COMMIT;

