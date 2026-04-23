<?php
/**
 * Script de ejecución de pruebas con generación de reportes
 * Versión mejorada: Guarda en BD + archivos JSON para desarrollo
 * Funciona tanto en local como en Railway
 *
 * Uso: php run-tests-reporter-db.php
 */

require_once 'includes/conexion.php';

define('BASE_PATH', __DIR__);
define('REPORTS_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'reports');
define('PHPUNIT_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'phpunit.phar');

// Crear directorios si no existen
if (!is_dir(REPORTS_DIR)) {
    mkdir(REPORTS_DIR, 0755, true);
}

$logsDir = REPORTS_DIR . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Timestamp para el reporte
$timestamp = date('Y-m-d_H-i-s');
$timestamp_readable = date('Y-m-d H:i:s');

echo "\n";
echo "========================================\n";
echo "TAURO STORE - TEST RUNNER\n";
echo "(Con persistencia en BD)\n";
echo "========================================\n";
echo "Fecha y hora: {$timestamp_readable}\n";
echo "Directorio: " . getcwd() . "\n";
echo "========================================\n\n";

// Archivos de reporte
$jsonReportFile = REPORTS_DIR . DIRECTORY_SEPARATOR . 'latest-report.json';
$archiveReportFile = REPORTS_DIR . DIRECTORY_SEPARATOR . "logs/report_{$timestamp}.json";

// Ejecutar PHPUnit
echo "Ejecutando pruebas...\n\n";

$testCommand = sprintf(
    'cd %s && php %s --testdox --log-json=%s 2>&1',
    BASE_PATH,
    PHPUNIT_PATH,
    escapeshellarg($jsonReportFile)
);

echo "Ejecutando: {$testCommand}\n\n";
$testOutput = [];
exec($testCommand, $testOutput, $returnCode);

// Mostrar salida de las pruebas
foreach ($testOutput as $line) {
    echo $line . "\n";
}

echo "\n========================================\n";

// Procesar resultados
if (file_exists($jsonReportFile)) {
    $jsonContent = file_get_contents($jsonReportFile);
    $testResults = json_decode($jsonContent, true);

    // Crear reporte mejorado
    $enhancedReport = [
        'timestamp' => $timestamp_readable,
        'timestamp_unix' => time(),
        'exit_code' => $returnCode,
        'status' => $returnCode === 0 ? 'EXITOSO' : 'FALLÓ',
        'tests' => $testResults,
        'php_version' => phpversion(),
        'platform' => php_uname(),
    ];

    // Contar resultados
    $total = 0;
    $passed = 0;
    $failed = 0;
    $skipped = 0;

    if (isset($testResults['tests'])) {
        $total = count($testResults['tests']);

        foreach ($testResults['tests'] as $test) {
            if (isset($test['status'])) {
                if ($test['status'] === 'pass') {
                    $passed++;
                } elseif ($test['status'] === 'fail') {
                    $failed++;
                } elseif ($test['status'] === 'skip') {
                    $skipped++;
                }
            }
        }
    }

    $successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;

    $enhancedReport['stats'] = [
        'total' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'skipped' => $skipped,
        'success_rate' => $successRate,
    ];

    echo "Resultados:\n";
    echo "  Total: {$total}\n";
    echo "  Exitosas: {$passed}\n";
    echo "  Fallidas: {$failed}\n";
    echo "  Saltadas: {$skipped}\n";
    echo "  Porcentaje: {$successRate}%\n";

    // Guardar en archivo JSON local (para desarrollo)
    file_put_contents($jsonReportFile, json_encode($enhancedReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    copy($jsonReportFile, $archiveReportFile);

    echo "\nReporte guardado en archivo: {$jsonReportFile}\n";
    echo "Archivo de historial: {$archiveReportFile}\n";

    // Guardar en BD (para Railway y persistencia)
    try {
        $sql = "
            INSERT INTO reportes_pruebas (
                timestamp, status, php_version, platform,
                total_tests, passed_tests, failed_tests, skipped_tests,
                success_rate, test_data, exit_code
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?
            )
        ";

        $stmt = $conn->prepare($sql);
        $testDataJson = json_encode($testResults);

        $stmt->execute([
            $timestamp_readable,
            $enhancedReport['status'],
            phpversion(),
            php_uname(),
            $total,
            $passed,
            $failed,
            $skipped,
            $successRate,
            $testDataJson,
            $returnCode,
        ]);

        echo "\nReporte guardado en BD (ID: " . $conn->lastInsertId() . ")\n";
        echo "Estado: El reporte persiste en Railway\n";

    } catch (Exception $e) {
        echo "\n⚠️ Advertencia: No se pudo guardar en BD:\n";
        echo "  " . $e->getMessage() . "\n";
        echo "Pero el reporte JSON fue guardado localmente.\n";
    }

} else {
    echo "ERROR: No se generó el archivo de reporte JSON.\n";
}

// Generar HTML de visualización
generateReportHTML();

echo "\nPanel de reportes: " . REPORTS_DIR . "/index.html\n";
echo "========================================\n\n";

exit($returnCode);

/**
 * Genera un archivo HTML para visualizar todos los reportes
 */
function generateReportHTML()
{
    global $conn;

    $logsDir = REPORTS_DIR . DIRECTORY_SEPARATOR . 'logs';
    $htmlFile = REPORTS_DIR . DIRECTORY_SEPARATOR . 'index-db.html';

    // Leer reportes de BD
    $reports = [];
    try {
        $stmt = $conn->query("SELECT * FROM reportes_pruebas ORDER BY timestamp DESC LIMIT 50");
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Si falla, intentar leer de archivos JSON
        if (is_dir($logsDir)) {
            $files = scandir($logsDir, SCANDIR_SORT_DESCENDING);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $filePath = $logsDir . DIRECTORY_SEPARATOR . $file;
                    $content = json_decode(file_get_contents($filePath), true);
                    if ($content) {
                        $reports[] = [
                            'timestamp' => $content['timestamp'] ?? 'Desconocido',
                            'status' => $content['status'] ?? 'Desconocido',
                            'success_rate' => $content['stats']['success_rate'] ?? 0,
                            'passed_tests' => $content['stats']['passed'] ?? 0,
                            'failed_tests' => $content['stats']['failed'] ?? 0,
                            'skipped_tests' => $content['stats']['skipped'] ?? 0,
                            'total_tests' => $content['stats']['total'] ?? 0,
                        ];
                    }
                }
            }
        }
    }

    // Generar HTML
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Pruebas - Tauro Store</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .storage-info {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-top: 15px;
            border-radius: 5px;
        }
        
        .storage-info strong {
            color: #667eea;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }
        
        .report-date {
            color: #667eea;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .report-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat {
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .success-rate {
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .success-rate-value {
            font-size: 24px;
            font-weight: bold;
        }
        
        .success-rate-value.high {
            color: #4caf50;
        }
        
        .success-rate-value.medium {
            color: #ff9800;
        }
        
        .success-rate-value.low {
            color: #f44336;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .status-badge.success {
            background: #4caf50;
            color: white;
        }
        
        .status-badge.failure {
            background: #f44336;
            color: white;
        }
        
        .no-reports {
            background: white;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
            color: #999;
        }
        
        .footer {
            text-align: center;
            color: white;
            padding: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reportes de Pruebas Unitarias</h1>
            <p>Tauro Store - Panel de Control de Calidad</p>
            <div class="storage-info">
                <strong>Almacenamiento:</strong> Base de datos + Archivos JSON<br>
                <strong>Ambiente:</strong>Compatible con Local y Railway<br>
                <strong>Persistencia:</strong>Datos guardados en BD
            </div>
        </div>
        
        <div class="reports-grid">
HTML;

    if (count($reports) > 0) {
        foreach ($reports as $report) {
            $timestamp = $report['timestamp'] ?? 'Desconocido';
            $status = $report['status'] ?? 'Desconocido';
            $successRate = $report['success_rate'] ?? 0;
            $passed = $report['passed_tests'] ?? 0;
            $failed = $report['failed_tests'] ?? 0;
            $skipped = $report['skipped_tests'] ?? 0;
            $total = $report['total_tests'] ?? 0;

            $statusClass = $status === 'EXITOSO' ? 'success' : 'failure';
            $rateClass = $successRate >= 80 ? 'high' : ($successRate >= 50 ? 'medium' : 'low');

            $html .= <<<HTML
            <div class="report-card">
                <div class="report-date">{$timestamp}</div>
                <span class="status-badge {$statusClass}">{$status}</span>
                
                <div class="success-rate">
                    <div class="success-rate-value {$rateClass}">{$successRate}%</div>
                </div>
                
                <div class="report-stats">
                    <div class="stat">
                        <div class="stat-label">Exitosas</div>
                        <div class="stat-value">{$passed}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Fallidas</div>
                        <div class="stat-value">{$failed}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Saltadas</div>
                        <div class="stat-value">{$skipped}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Total</div>
                        <div class="stat-value">{$total}</div>
                    </div>
                </div>
            </div>
HTML;
        }
    } else {
        $html .= <<<HTML
            <div class="no-reports">
                <p>No hay reportes disponibles aún.</p>
                <p>Ejecuta las pruebas para generar reportes.</p>
            </div>
HTML;
    }

    $html .= <<<'HTML'
        </div>
        
        <div class="footer">
            <p>Panel en tiempo real | Datos persistidos en BD | Compatible con Railway</p>
        </div>
    </div>
</body>
</html>
HTML;

    file_put_contents($htmlFile, $html);
}

