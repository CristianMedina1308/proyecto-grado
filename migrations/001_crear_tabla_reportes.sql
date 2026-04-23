-- Migración para tabla de reportes de pruebas
-- Ejecutar esta migración en la BD (local y Railway)

CREATE TABLE IF NOT EXISTS reportes_pruebas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL COMMENT 'EXITOSO o FALLÓ',
    php_version VARCHAR(20),
    platform VARCHAR(100),
    total_tests INT DEFAULT 0,
    passed_tests INT DEFAULT 0,
    failed_tests INT DEFAULT 1,
    skipped_tests INT DEFAULT 0,
    success_rate DECIMAL(5, 2) DEFAULT 0,
    test_data LONGTEXT COMMENT 'JSON con detalles de todas las pruebas',
    exit_code INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp DESC),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista para últimos reportes
CREATE OR REPLACE VIEW v_ultimo_reporte AS
SELECT *
FROM reportes_pruebas
ORDER BY timestamp DESC
LIMIT 1;

