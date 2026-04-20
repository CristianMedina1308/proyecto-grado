-- Ejecuta este script sobre la base de datos donde tengas las tablas (por ejemplo: tiendaropa / railway).
-- Si tu cliente SQL no selecciona la BD automáticamente, descomenta el USE y ajusta el nombre.
-- USE tiendaropa;

START TRANSACTION;

ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS sku VARCHAR(40) NULL AFTER nombre,
  ADD COLUMN IF NOT EXISTS marca VARCHAR(80) NULL AFTER categoria,
  ADD COLUMN IF NOT EXISTS color VARCHAR(50) NULL AFTER marca,
  ADD COLUMN IF NOT EXISTS material VARCHAR(100) NULL AFTER color,
  ADD COLUMN IF NOT EXISTS fit VARCHAR(30) NULL AFTER material;

ALTER TABLE productos
  ADD UNIQUE KEY IF NOT EXISTS uq_productos_sku (sku);

UPDATE productos
SET sku = CONCAT('TS-', LPAD(id, 5, '0'))
WHERE sku IS NULL OR TRIM(sku) = '';

UPDATE productos
SET marca = COALESCE(NULLIF(TRIM(marca), ''), 'Tauro');

UPDATE productos
SET fit = COALESCE(NULLIF(TRIM(fit), ''),
  CASE
    WHEN categoria IN ('Camisetas y Polos', 'Chaquetas y Buzos') THEN 'Regular'
    WHEN categoria = 'Pantalones' THEN 'Slim'
    ELSE 'Regular'
  END
);

UPDATE productos
SET material = COALESCE(NULLIF(TRIM(material), ''),
  CASE
    WHEN categoria = 'Calzado' THEN 'Sintetico premium'
    WHEN categoria = 'Accesorios' THEN 'Material mixto'
    ELSE 'Algodon premium'
  END
);

UPDATE productos
SET color = COALESCE(NULLIF(TRIM(color), ''),
  CASE
    WHEN LOWER(nombre) LIKE '%negra%' OR LOWER(nombre) LIKE '%negro%' THEN 'Negro'
    WHEN LOWER(nombre) LIKE '%blanca%' OR LOWER(nombre) LIKE '%blanco%' THEN 'Blanco'
    WHEN LOWER(nombre) LIKE '%azul%' THEN 'Azul'
    WHEN LOWER(nombre) LIKE '%caqui%' THEN 'Caqui'
    WHEN LOWER(nombre) LIKE '%gris%' THEN 'Gris'
    WHEN LOWER(nombre) LIKE '%arena%' THEN 'Arena'
    ELSE 'Multicolor'
  END
);

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS subtotal_productos DECIMAL(10,2) NULL DEFAULT 0 AFTER total,
  ADD COLUMN IF NOT EXISTS iva_rate DECIMAL(6,4) NULL DEFAULT NULL AFTER subtotal_productos,
  ADD COLUMN IF NOT EXISTS iva_monto DECIMAL(10,2) NULL DEFAULT NULL AFTER iva_rate,
  ADD COLUMN IF NOT EXISTS costo_envio DECIMAL(10,2) NULL DEFAULT 0 AFTER subtotal_productos,
  ADD COLUMN IF NOT EXISTS zona_envio VARCHAR(100) NULL AFTER ciudad_envio,
  ADD COLUMN IF NOT EXISTS dias_entrega_min INT NULL AFTER zona_envio,
  ADD COLUMN IF NOT EXISTS dias_entrega_max INT NULL AFTER dias_entrega_min,
  ADD COLUMN IF NOT EXISTS stock_reintegrado TINYINT(1) NOT NULL DEFAULT 0 AFTER dias_entrega_max,
  ADD COLUMN IF NOT EXISTS estado_pendiente_at DATETIME NULL AFTER stock_reintegrado,
  ADD COLUMN IF NOT EXISTS estado_pagado_at DATETIME NULL AFTER estado_pendiente_at,
  ADD COLUMN IF NOT EXISTS estado_preparando_at DATETIME NULL AFTER estado_pagado_at,
  ADD COLUMN IF NOT EXISTS estado_enviado_at DATETIME NULL AFTER estado_preparando_at,
  ADD COLUMN IF NOT EXISTS estado_entregado_at DATETIME NULL AFTER estado_enviado_at,
  ADD COLUMN IF NOT EXISTS estado_cancelado_at DATETIME NULL AFTER estado_entregado_at;

-- PIN de recuperación (4 dígitos)
-- Se guarda hasheado (nunca en texto plano) y se controla fuerza bruta con intentos/bloqueo.
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS recovery_pin_hash VARCHAR(255) NULL AFTER password,
  ADD COLUMN IF NOT EXISTS recovery_pin_set_at DATETIME NULL AFTER recovery_pin_hash,
  ADD COLUMN IF NOT EXISTS pin_failed_attempts INT NOT NULL DEFAULT 0 AFTER recovery_pin_set_at,
  ADD COLUMN IF NOT EXISTS pin_locked_until DATETIME NULL AFTER pin_failed_attempts;

UPDATE pedidos
SET estado = 'pagado'
WHERE estado = 'aprobado';

UPDATE pedidos
SET subtotal_productos = COALESCE(subtotal_productos, total),
    costo_envio = COALESCE(costo_envio, 0);

UPDATE pedidos
SET estado_pendiente_at = COALESCE(estado_pendiente_at, fecha)
WHERE estado IN ('pendiente','pagado','preparando','enviado','entregado','cancelado');

UPDATE pedidos
SET estado_pagado_at = COALESCE(estado_pagado_at, fecha)
WHERE estado IN ('pagado','preparando','enviado','entregado');

UPDATE pedidos
SET estado_preparando_at = COALESCE(estado_preparando_at, fecha)
WHERE estado IN ('preparando','enviado','entregado');

UPDATE pedidos
SET estado_enviado_at = COALESCE(estado_enviado_at, fecha)
WHERE estado IN ('enviado','entregado');

UPDATE pedidos
SET estado_entregado_at = COALESCE(estado_entregado_at, fecha)
WHERE estado = 'entregado';

UPDATE pedidos
SET estado_cancelado_at = COALESCE(estado_cancelado_at, fecha)
WHERE estado = 'cancelado';

CREATE TABLE IF NOT EXISTS pedido_estados_historial (
  id INT(11) NOT NULL AUTO_INCREMENT,
  pedido_id INT(11) NOT NULL,
  estado VARCHAR(50) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id INT(11) NULL,
  origen VARCHAR(20) NOT NULL DEFAULT 'sistema',
  nota VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_historial_pedido (pedido_id),
  KEY idx_historial_estado (estado),
  CONSTRAINT fk_historial_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO pedido_estados_historial (pedido_id, estado, fecha, usuario_id, origen, nota)
SELECT p.id,
       p.estado,
       COALESCE(
         CASE p.estado
           WHEN 'cancelado' THEN p.estado_cancelado_at
           WHEN 'entregado' THEN p.estado_entregado_at
           WHEN 'enviado' THEN p.estado_enviado_at
           WHEN 'preparando' THEN p.estado_preparando_at
           WHEN 'pagado' THEN p.estado_pagado_at
           ELSE p.estado_pendiente_at
         END,
         p.fecha,
         NOW()
       ) AS fecha_estado,
       p.usuario_id,
       'sistema',
       'Estado inicial migrado'
FROM pedidos p
WHERE NOT EXISTS (
  SELECT 1
  FROM pedido_estados_historial h
  WHERE h.pedido_id = p.id
);

CREATE TABLE IF NOT EXISTS tarifas_envio (
  id INT(11) NOT NULL AUTO_INCREMENT,
  ciudad VARCHAR(100) NOT NULL,
  zona VARCHAR(100) NOT NULL,
  costo DECIMAL(10,2) NOT NULL,
  dias_min INT NOT NULL DEFAULT 1,
  dias_max INT NOT NULL DEFAULT 3,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tarifa_ciudad_zona (ciudad, zona)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO tarifas_envio (ciudad, zona, costo, dias_min, dias_max, activo) VALUES
('bogota', 'norte', 12000, 1, 2, 1),
('bogota', 'centro', 10000, 1, 2, 1),
('bogota', 'sur', 14000, 1, 2, 1),
('bogota', 'occidente', 13000, 1, 2, 1),
('bogota', 'oriente', 13000, 1, 2, 1),
('medellin', 'norte', 13000, 1, 2, 1),
('medellin', 'centro', 12000, 1, 2, 1),
('medellin', 'sur', 14000, 1, 2, 1),
('cali', 'norte', 13000, 1, 3, 1),
('cali', 'centro', 12000, 1, 3, 1),
('cali', 'sur', 14000, 1, 3, 1),
('barranquilla', 'norte', 15000, 2, 4, 1),
('barranquilla', 'centro', 14000, 2, 4, 1),
('barranquilla', 'sur', 16000, 2, 4, 1),
('otras', 'estandar', 19000, 2, 5, 1)
ON DUPLICATE KEY UPDATE
  costo = VALUES(costo),
  dias_min = VALUES(dias_min),
  dias_max = VALUES(dias_max),
  activo = VALUES(activo);

COMMIT;

SET @tbl_resenas := (
  SELECT TABLE_NAME
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'rese%'
  LIMIT 1
);

SET @sql_add_col := CONCAT(
  'ALTER TABLE `', REPLACE(@tbl_resenas, '`', '``'), '` ',
  'ADD COLUMN IF NOT EXISTS compra_verificada TINYINT(1) NOT NULL DEFAULT 0 AFTER comentario'
);
PREPARE stmt1 FROM @sql_add_col;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql_add_idx := CONCAT(
  'ALTER TABLE `', REPLACE(@tbl_resenas, '`', '``'), '` ',
  'ADD UNIQUE KEY IF NOT EXISTS uq_resena_usuario_producto (producto_id, usuario_id)'
);
PREPARE stmt2 FROM @sql_add_idx;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @sql_update := CONCAT(
  'UPDATE `', REPLACE(@tbl_resenas, '`', '``'), '` r ',
  'JOIN pedidos p ON p.usuario_id = r.usuario_id ',
  'JOIN detalle_pedido dp ON dp.pedido_id = p.id AND dp.producto_id = r.producto_id ',
  \"SET r.compra_verificada = 1 WHERE p.estado = 'entregado'\"
);
PREPARE stmt3 FROM @sql_update;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;
