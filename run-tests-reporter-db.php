<?php
/**
 * Script de ejecución de pruebas con generación de reportes
 * Versión mejorada: Guarda en BD + archivos JSON para desarrollo
 * Funciona tanto en local como en Railway
 *
 * Uso: php run-tests-reporter-db.php
 */

require_once 'includes/conexion.php';

// Asegurar zona horaria consistente para timestamps (evita “fecha mala”)
// Puedes sobre-escribir con variable de entorno APP_TIMEZONE.
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Bogota');

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
$junitReportFile = REPORTS_DIR . DIRECTORY_SEPARATOR . 'latest-junit.xml';
$archiveJunitFile = REPORTS_DIR . DIRECTORY_SEPARATOR . "logs/junit_{$timestamp}.xml";

// Ejecutar PHPUnit
echo "Ejecutando pruebas...\n\n";

// Evitar que se usen archivos viejos si el comando falla
@unlink($junitReportFile);
@unlink($jsonReportFile);

$cdCommand = (DIRECTORY_SEPARATOR === '\\') ? 'cd /d %s' : 'cd %s';

$testCommand = sprintf(
    $cdCommand . ' && php %s --testdox --colors=never --do-not-fail-on-warning --do-not-fail-on-phpunit-warning --log-junit %s tests/ 2>&1',
    escapeshellarg(BASE_PATH),
    escapeshellarg(PHPUNIT_PATH),
    escapeshellarg($junitReportFile)
);

echo "Ejecutando: {$testCommand}\n\n";
$testOutput = [];
exec($testCommand, $testOutput, $returnCode);

// Mostrar salida de las pruebas
foreach ($testOutput as $line) {
    echo $line . "\n";
}

$joinedOutput = implode("\n", $testOutput);
$phpunitWarnings = 0;
if (preg_match('/PHPUnit\s+Warnings:\s*(\d+)/i', $joinedOutput, $m)) {
    $phpunitWarnings = (int) $m[1];
}

echo "\n========================================\n";

// Procesar resultados
if (file_exists($junitReportFile)) {
    // Copiar el XML al historial (útil para debug)
    @copy($junitReportFile, $archiveJunitFile);

    $parsed = parseJUnitReport($junitReportFile);
    $testResults = [
        // Mantener el formato esperado por el panel/API: {"tests": [...]}
        'tests' => $parsed['tests'],
        'source' => 'junit',
    ];

    // Crear reporte mejorado
    $enhancedReport = [
        'timestamp' => $timestamp_readable,
        'timestamp_unix' => time(),
        'exit_code' => $returnCode,
        // Se recalcula después en base a fallos reales
        'status' => $returnCode === 0 ? 'EXITOSO' : 'FALLÓ',
        'tests' => $testResults,
        'php_version' => phpversion(),
        'platform' => php_uname(),
        'phpunit_warnings' => $phpunitWarnings,
    ];

    // Contar resultados (ya viene del parser)
    $total = (int) ($parsed['stats']['total'] ?? 0);
    $passed = (int) ($parsed['stats']['passed'] ?? 0);
    $failed = (int) ($parsed['stats']['failed'] ?? 0);
    $skipped = (int) ($parsed['stats']['skipped'] ?? 0);

    $successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;

    $enhancedReport['stats'] = [
        'total' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'skipped' => $skipped,
        'success_rate' => $successRate,
    ];

    // Ajustar status: si no hay fallos, considerar EXITOSO aunque existan warnings
    $enhancedReport['status'] = $failed === 0 ? 'EXITOSO' : 'FALLÓ';

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
        $testDataJson = json_encode($testResults, JSON_UNESCAPED_SLASHES);

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
    echo "ERROR: No se generó el archivo JUnit XML (phpunit).\n";
    echo "Sugerencia: ejecuta `php tools/phpunit.phar --help` y verifica que exista la opción --log-junit.\n";
}

// Generar HTML de visualización
generateReportHTML();

echo "\nPanel de reportes: " . REPORTS_DIR . "/index-db.html\n";
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
        $stmt = $conn->query("SELECT id, timestamp, status, success_rate, passed_tests, failed_tests, skipped_tests, total_tests FROM reportes_pruebas ORDER BY timestamp DESC LIMIT 50");
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

    // Generar HTML (con estilo del sitio)
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Pruebas - Tauro Store</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .reports-page {
            padding: 28px 16px;
            max-width: 1180px;
            margin: 0 auto;
        }

        .reports-hero {
            background: var(--bg-surface);
            border: 1px solid rgba(215, 181, 109, 0.28);
            border-radius: var(--radius-lg);
            padding: 22px 22px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 18px;
        }

        .reports-hero p {
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .reports-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .btn {
            appearance: none;
            border: 1px solid rgba(184, 146, 71, 0.45);
            background: rgba(184, 146, 71, 0.12);
            color: var(--text-primary);
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, rgba(184, 146, 71, 0.98), rgba(138, 101, 33, 0.92));
            color: var(--text-inverse);
            border-color: rgba(138, 101, 33, 0.6);
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .report-card {
            background: var(--bg-surface);
            border: 1px solid rgba(215, 181, 109, 0.28);
            border-radius: var(--radius-lg);
            padding: 16px;
            box-shadow: 0 16px 34px rgba(12, 9, 6, 0.08);
        }

        .report-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .report-date {
            font-weight: 800;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .status-badge.success {
            background: rgba(59, 153, 96, 0.16);
            color: #1f6b3c;
            border: 1px solid rgba(59, 153, 96, 0.35);
        }

        .status-badge.failure {
            background: rgba(215, 68, 66, 0.12);
            color: #a62a28;
            border: 1px solid rgba(215, 68, 66, 0.35);
        }

        .report-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 10px 0 14px;
        }

        .stat {
            background: rgba(23, 19, 15, 0.04);
            border: 1px solid rgba(216, 200, 173, 0.55);
            border-radius: 14px;
            padding: 10px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .stat-value {
            font-size: 20px;
            font-weight: 900;
        }

        .success-rate {
            background: rgba(184, 146, 71, 0.10);
            border: 1px solid rgba(184, 146, 71, 0.25);
            border-radius: 14px;
            padding: 10px;
            margin-top: 8px;
        }

        .success-rate-value {
            font-size: 22px;
            font-weight: 900;
        }

        .muted {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .is-hidden {
            display: none;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(10, 8, 6, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 9999;
        }

        .modal {
            width: min(920px, 100%);
            max-height: 84vh;
            overflow: auto;
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(215, 181, 109, 0.28);
            box-shadow: var(--shadow-soft);
            padding: 18px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .tests-list {
            margin-top: 10px;
            display: grid;
            gap: 8px;
        }

        .test-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 10px;
            border-radius: 14px;
            border: 1px solid rgba(216, 200, 173, 0.55);
            background: rgba(23, 19, 15, 0.03);
        }

        .test-name {
            font-weight: 700;
            word-break: break-word;
        }

        .test-status {
            font-weight: 900;
            white-space: nowrap;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid rgba(216, 200, 173, 0.6);
            background: rgba(255, 255, 255, 0.6);
        }

        .test-status.pass { color: #1f6b3c; border-color: rgba(59,153,96,.35); background: rgba(59,153,96,.12); }
        .test-status.fail { color: #a62a28; border-color: rgba(215,68,66,.35); background: rgba(215,68,66,.10); }
        .test-status.skip { color: #6a4a12; border-color: rgba(184,146,71,.35); background: rgba(184,146,71,.10); }
    </style>
</head>
<body>
    <main class="reports-page">
        <section class="reports-hero">
            <h1>Reportes de Pruebas Unitarias</h1>
            <p>Panel de control de calidad. Los reportes se guardan en <strong>Base de Datos</strong> (historial) y en <strong>JSON</strong> (respaldo).</p>
            <div class="reports-actions">
                <button class="btn btn-primary" id="toggleHistoryBtn" type="button">Mostrar historial</button>
                <a class="btn" href="api-db.php?action=latest">API: último reporte</a>
                <a class="btn" href="api-db.php?action=list">API: listar</a>
            </div>
            <p class="muted" style="margin-top:10px;">
                Nota: se muestran varios porque se guarda el <strong>historial</strong> de ejecuciones. Por defecto se ve el último.
            </p>
        </section>

        <section class="reports-grid" id="reportsGrid">
HTML;

    if (count($reports) > 0) {
        $i = 0;
        foreach ($reports as $report) {
            $i++;
            $id = $report['id'] ?? null;
            $timestamp = $report['timestamp'] ?? 'Desconocido';
            $status = $report['status'] ?? 'Desconocido';
            $successRate = $report['success_rate'] ?? 0;
            $passed = $report['passed_tests'] ?? 0;
            $failed = $report['failed_tests'] ?? 0;
            $skipped = $report['skipped_tests'] ?? 0;
            $total = $report['total_tests'] ?? 0;

            $statusClass = $status === 'EXITOSO' ? 'success' : 'failure';
            $rateClass = $successRate >= 80 ? 'high' : ($successRate >= 50 ? 'medium' : 'low');

            $hiddenClass = $i === 1 ? '' : 'is-hidden';
            $dataId = $id ? "data-report-id=\"{$id}\"" : '';

            // Formato DD/MM/YYYY HH:MM:SS
            $formattedTimestamp = $timestamp;
            try {
                $dt = new DateTime($timestamp);
                $formattedTimestamp = $dt->format('d/m/Y H:i:s');
            } catch (Exception $e) {
                // mantener original
            }

            $html .= <<<HTML
            <article class="report-card {$hiddenClass}" {$dataId}>
                <div class="report-meta">
                    <div>
                        <div class="report-date">{$formattedTimestamp}</div>
                        <div class="muted">Reporte ID: {$id}</div>
                    </div>
                    <span class="status-badge {$statusClass}">{$status}</span>
                </div>

                <div class="success-rate">
                    <div class="muted">Tasa de éxito</div>
                    <div class="success-rate-value {$rateClass}">{$successRate}%</div>
                </div>

                <div class="report-stats">
                    <div class="stat"><div class="stat-label">Exitosas</div><div class="stat-value">{$passed}</div></div>
                    <div class="stat"><div class="stat-label">Fallidas</div><div class="stat-value">{$failed}</div></div>
                    <div class="stat"><div class="stat-label">Saltadas</div><div class="stat-value">{$skipped}</div></div>
                    <div class="stat"><div class="stat-label">Total</div><div class="stat-value">{$total}</div></div>
                </div>

                <button class="btn btn-primary" type="button" onclick="openDetails({$id})">Ver pruebas ejecutadas</button>
            </article>
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
        </section>

        <div class="muted" style="margin-top:18px; text-align:center;">Generado automáticamente | Persistencia en BD | Compatible con Railway</div>
    </main>

    <div class="modal-backdrop" id="modalBackdrop" role="dialog" aria-modal="true">
        <div class="modal">
            <div class="modal-header">
                <h2 style="margin:0;">Pruebas ejecutadas</h2>
                <button class="btn" type="button" onclick="closeModal()">Cerrar</button>
            </div>
            <div class="muted" id="modalMeta"></div>
            <div id="modalBody" class="tests-list" style="margin-top:12px;"></div>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('toggleHistoryBtn');
        let showingHistory = false;

        toggleBtn?.addEventListener('click', () => {
            showingHistory = !showingHistory;
            document.querySelectorAll('.report-card.is-hidden').forEach(el => {
                el.style.display = showingHistory ? '' : 'none';
            });
            toggleBtn.textContent = showingHistory ? 'Ocultar historial' : 'Mostrar historial';
        });

        // Ocultar historial al cargar
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.report-card.is-hidden').forEach(el => {
                el.style.display = 'none';
            });
        });

        const backdrop = document.getElementById('modalBackdrop');
        const modalBody = document.getElementById('modalBody');
        const modalMeta = document.getElementById('modalMeta');

        function closeModal() {
            backdrop.style.display = 'none';
            modalBody.innerHTML = '';
            modalMeta.textContent = '';
        }

        backdrop?.addEventListener('click', (e) => {
            if (e.target === backdrop) closeModal();
        });

        async function openDetails(id) {
            if (!id) return;
            backdrop.style.display = 'flex';
            modalBody.innerHTML = '<div class="muted">Cargando...</div>';
            modalMeta.textContent = `Reporte ID: ${id}`;

            try {
                const res = await fetch(`api-db.php?action=by-id&id=${encodeURIComponent(id)}`);
                const data = await res.json();
                if (!data.success) {
                    modalBody.innerHTML = `<div class="muted">Error: ${data.error || 'No se pudo cargar'}</div>`;
                    return;
                }

                const report = data.report || {};
                const tests = report.tests || (report.test_data && report.test_data.tests) || [];
                const when = report.timestamp ? report.timestamp : '';
                modalMeta.textContent = `Reporte ID: ${id}${when ? ' | ' + when : ''}`;

                if (!tests || tests.length === 0) {
                    modalBody.innerHTML = '<div class="muted">Este reporte no tiene detalle de pruebas.</div>';
                    return;
                }

                modalBody.innerHTML = tests.map(t => {
                    const status = (t.status || '').toLowerCase();
                    const name = t.name || 'Test';
                    const msg = t.message ? `<div class="muted" style="margin-top:4px;">${escapeHtml(String(t.message))}</div>` : '';
                    return `
                        <div class="test-row">
                            <div>
                                <div class="test-name">${escapeHtml(String(name))}</div>
                                ${msg}
                            </div>
                            <div class="test-status ${status}">${(status || 'pass').toUpperCase()}</div>
                        </div>
                    `;
                }).join('');

            } catch (err) {
                modalBody.innerHTML = `<div class="muted">Error al cargar: ${escapeHtml(String(err))}</div>`;
            }
        }

        function escapeHtml(str) {
            return str.replace(/[&<>'"]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[c]));
        }
    </script>
</body>
</html>
HTML;

    file_put_contents($htmlFile, $html);
}

/**
 * Parsea un archivo JUnit XML generado por PHPUnit (v9+ / v10+ / v11)
 * y lo normaliza a una lista de tests con estados pass/fail/skip.
 *
 * @return array{tests: array<int, array<string,mixed>>, stats: array{total:int, passed:int, failed:int, skipped:int}}
 */
function parseJUnitReport(string $junitFile): array
{
    $tests = [];
    $total = 0;
    $passed = 0;
    $failed = 0;
    $skipped = 0;

    if (!file_exists($junitFile)) {
        return [
            'tests' => [],
            'stats' => [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'skipped' => 0,
            ],
        ];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($junitFile);
    if ($xml === false) {
        return [
            'tests' => [],
            'stats' => [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'skipped' => 0,
            ],
        ];
    }

    // Buscar todos los <testcase> sin depender de la raíz (testsuites/testsuit)
    $testcases = $xml->xpath('//testcase') ?: [];

    foreach ($testcases as $case) {
        $total++;

        $name = (string) ($case['name'] ?? '');
        $classname = (string) ($case['classname'] ?? '');
        $time = isset($case['time']) ? (float) $case['time'] : null;

        $status = 'pass';
        $message = null;

        // En JUnit: <failure>, <error>, <skipped>
        if (isset($case->skipped)) {
            $status = 'skip';
            $skipped++;
            $message = (string) ($case->skipped['message'] ?? '') ?: trim((string) $case->skipped);
        } elseif (isset($case->failure)) {
            $status = 'fail';
            $failed++;
            $message = (string) ($case->failure['message'] ?? '') ?: trim((string) $case->failure);
        } elseif (isset($case->error)) {
            $status = 'fail';
            $failed++;
            $message = (string) ($case->error['message'] ?? '') ?: trim((string) $case->error);
        } else {
            $passed++;
        }

        $displayName = $classname !== '' ? ($classname . '::' . $name) : $name;

        $row = [
            'name' => $displayName !== '' ? $displayName : 'Test',
            'status' => $status,
        ];

        if ($classname !== '') {
            $row['classname'] = $classname;
        }
        if ($time !== null) {
            $row['time'] = $time;
        }
        if ($message) {
            // Truncar mensajes muy largos para que el JSON/BD no explote
            $row['message'] = function_exists('mb_substr') ? mb_substr($message, 0, 2000) : substr($message, 0, 2000);
        }

        $tests[] = $row;
    }

    return [
        'tests' => $tests,
        'stats' => [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
        ],
    ];
}

