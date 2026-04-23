<?php
/**
 * Script de ejecución de pruebas con generación de reportes
 * Ejecuta PHPUnit y guarda los resultados en JSON con timestamp
 *
 * Uso: php run-tests-reporter.php
 */

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
echo "========================================\n";
echo "Fecha y hora: {$timestamp_readable}\n";
echo "Directorio: " . getcwd() . "\n";
echo "========================================\n\n";

// Archivos de reporte
$jsonReportFile = REPORTS_DIR . DIRECTORY_SEPARATOR . 'latest-report.json';
$archiveReportFile = REPORTS_DIR . DIRECTORY_SEPARATOR . "logs/report_{$timestamp}.json";
$htmlReportFile = REPORTS_DIR . DIRECTORY_SEPARATOR . 'index.html';

// Ejecutar PHPUnit y capturar salida
echo "Ejecutando pruebas...\n\n";

// Crear comando PHPUnit
$command = sprintf(
    'php %s --version',
    PHPUNIT_PATH
);

$output = [];
$returnCode = 0;

// Ejecutar pruebas
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
    if (isset($testResults['tests'])) {
        $total = count($testResults['tests']);
        $passed = 0;
        $failed = 0;
        $skipped = 0;

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

        $enhancedReport['stats'] = [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'success_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
        ];

        echo "Resultados:\n";
        echo "  Total: {$total}\n";
        echo "  Exitosas: {$passed}\n";
        echo "  Fallidas: {$failed}\n";
        echo "  Saltadas: {$skipped}\n";
        echo "  Porcentaje: " . $enhancedReport['stats']['success_rate'] . "%\n";
    }

    // Guardar reporte JSON mejorado
    file_put_contents($jsonReportFile, json_encode($enhancedReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Guardar archivo en historial
    copy($jsonReportFile, $archiveReportFile);

    echo "\nReporte guardado: {$jsonReportFile}\n";
    echo "Archivo de historial: {$archiveReportFile}\n";

} else {
    echo "ERROR: No se generó el archivo de reporte JSON.\n";
}

// Generar HTML de visualización
generateReportHTML();

echo "\nPanel de reportes: {$htmlReportFile}\n";
echo "========================================\n\n";

exit($returnCode);

/**
 * Genera un archivo HTML para visualizar todos los reportes
 */
function generateReportHTML()
{
    $logsDir = REPORTS_DIR . DIRECTORY_SEPARATOR . 'logs';
    $htmlFile = REPORTS_DIR . DIRECTORY_SEPARATOR . 'index.html';

    // Leer todos los reportes
    $reports = [];
    if (is_dir($logsDir)) {
        $files = scandir($logsDir, SCANDIR_SORT_DESCENDING);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filePath = $logsDir . DIRECTORY_SEPARATOR . $file;
                $content = json_decode(file_get_contents($filePath), true);
                if ($content) {
                    $reports[] = [
                        'filename' => $file,
                        'data' => $content,
                    ];
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
        
        .stat-passed {
            border-left: 4px solid #4caf50;
        }
        
        .stat-failed {
            border-left: 4px solid #f44336;
        }
        
        .stat-skipped {
            border-left: 4px solid #ff9800;
        }
        
        .success-rate {
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .success-rate-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
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
        
        .view-details {
            display: block;
            width: 100%;
            padding: 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-weight: bold;
            transition: background 0.2s;
        }
        
        .view-details:hover {
            background: #5568d3;
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
        
        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reportes de Pruebas Unitarias</h1>
            <p>Tauro Store - Panel de Control de Calidad</p>
        </div>
        
        <div class="reports-grid">
HTML;

    if (count($reports) > 0) {
        foreach ($reports as $report) {
            $data = $report['data'];
            $timestamp = $data['timestamp'] ?? 'Desconocido';
            $status = $data['status'] ?? 'Desconocido';

            $stats = $data['stats'] ?? [];
            $total = $stats['total'] ?? 0;
            $passed = $stats['passed'] ?? 0;
            $failed = $stats['failed'] ?? 0;
            $skipped = $stats['skipped'] ?? 0;
            $successRate = $stats['success_rate'] ?? 0;

            $statusClass = $status === 'EXITOSO' ? 'success' : 'failure';
            $rateClass = $successRate >= 80 ? 'high' : ($successRate >= 50 ? 'medium' : 'low');

            $html .= <<<HTML
            <div class="report-card">
                <div class="report-date">{$timestamp}</div>
                <span class="status-badge {$statusClass}">{$status}</span>
                
                <div class="success-rate">
                    <div class="success-rate-label">Tasa de Éxito</div>
                    <div class="success-rate-value {$rateClass}">{$successRate}%</div>
                </div>
                
                <div class="report-stats">
                    <div class="stat stat-passed">
                        <div class="stat-label">Exitosas</div>
                        <div class="stat-value">{$passed}</div>
                    </div>
                    <div class="stat stat-failed">
                        <div class="stat-label">Fallidas</div>
                        <div class="stat-value">{$failed}</div>
                    </div>
                    <div class="stat stat-skipped">
                        <div class="stat-label">Saltadas</div>
                        <div class="stat-value">{$skipped}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Total</div>
                        <div class="stat-value">{$total}</div>
                    </div>
                </div>
                
                <button class="view-details" onclick="showDetails('{$report['filename']}')">
                    Ver Detalles
                </button>
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
            <p>Últimas pruebas ejecutadas | Generado automáticamente por el sistema</p>
        </div>
    </div>
    
    <script>
        function showDetails(filename) {
            const reportPath = 'logs/' + filename;
            fetch(reportPath)
                .then(response => response.json())
                .then(data => {
                    let details = `
TIMESTMATP: ${data.timestamp}
EXIT CODE: ${data.exit_code}
STATUS: ${data.status}
PHP VERSION: ${data.php_version}

ESTADÍSTICAS:
- Total: ${data.stats.total}
- Exitosas: ${data.stats.passed}
- Fallidas: ${data.stats.failed}
- Saltadas: ${data.stats.skipped}
- Tasa de Éxito: ${data.stats.success_rate}%
`;
                    
                    if (data.tests && data.tests.tests) {
                        details += '\n\nDETALLES DE PRUEBAS:\n';
                        data.tests.tests.forEach(test => {
                            details += `\n- ${test.name}: ${test.status.toUpperCase()}`;
                            if (test.message) {
                                details += ` (${test.message})`;
                            }
                        });
                    }
                    
                    alert(details);
                })
                .catch(error => {
                    alert('Error al cargar detalles: ' + error);
                });
        }
    </script>
</body>
</html>
HTML;

    file_put_contents($htmlFile, $html);
}

