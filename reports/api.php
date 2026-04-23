<?php
/**
 * API de Reportes de Pruebas
 * Proporciona acceso a los reportes guardados en formato JSON
 *
 * Uso:
 * - GET /reports/api.php?action=list - Lista todos los reportes
 * - GET /reports/api.php?action=latest - Obtiene el último reporte
 * - GET /reports/api.php?action=get&file=report_2026-04-23_15-30-45.json - Obtiene un reporte específico
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('REPORTS_DIR', __DIR__);
define('LOGS_DIR', REPORTS_DIR . DIRECTORY_SEPARATOR . 'logs');

// Acción solicitada
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

try {
    switch ($action) {
        case 'list':
            listReports();
            break;

        case 'latest':
            getLatestReport();
            break;

        case 'get':
            getReport($_GET['file'] ?? '');
            break;

        case 'stats':
            getStats();
            break;

        default:
            error('Acción no válida');
    }
} catch (Exception $e) {
    error($e->getMessage());
}

/**
 * Lista todos los reportes disponibles
 */
function listReports()
{
    $reports = [];

    if (is_dir(LOGS_DIR)) {
        $files = scandir(LOGS_DIR, SCANDIR_SORT_DESCENDING);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json' && is_file(LOGS_DIR . DIRECTORY_SEPARATOR . $file)) {
                $filePath = LOGS_DIR . DIRECTORY_SEPARATOR . $file;
                $content = @json_decode(file_get_contents($filePath), true);

                if ($content) {
                    $reports[] = [
                        'filename' => $file,
                        'timestamp' => $content['timestamp'] ?? null,
                        'timestamp_unix' => $content['timestamp_unix'] ?? null,
                        'status' => $content['status'] ?? null,
                        'stats' => $content['stats'] ?? null,
                        'file_size' => filesize($filePath),
                    ];
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'total' => count($reports),
        'reports' => $reports,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Obtiene el último reporte
 */
function getLatestReport()
{
    $latestFile = REPORTS_DIR . DIRECTORY_SEPARATOR . 'latest-report.json';

    if (!file_exists($latestFile)) {
        error('No hay reportes disponibles');
        return;
    }

    $content = @json_decode(file_get_contents($latestFile), true);

    if (!$content) {
        error('Error al leer el reporte');
        return;
    }

    echo json_encode([
        'success' => true,
        'report' => $content,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Obtiene un reporte específico
 */
function getReport($filename)
{
    // Validar nombre de archivo
    if (!preg_match('/^report_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $filename)) {
        error('Nombre de archivo no válido');
        return;
    }

    $filePath = LOGS_DIR . DIRECTORY_SEPARATOR . $filename;

    if (!file_exists($filePath)) {
        error('Reporte no encontrado');
        return;
    }

    $content = @json_decode(file_get_contents($filePath), true);

    if (!$content) {
        error('Error al leer el reporte');
        return;
    }

    echo json_encode([
        'success' => true,
        'report' => $content,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Obtiene estadísticas generales de todos los reportes
 */
function getStats()
{
    $reports = [];
    $totalReports = 0;
    $totalPassed = 0;
    $totalFailed = 0;
    $avgSuccessRate = 0;

    if (is_dir(LOGS_DIR)) {
        $files = scandir(LOGS_DIR, SCANDIR_SORT_DESCENDING);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filePath = LOGS_DIR . DIRECTORY_SEPARATOR . $file;
                $content = @json_decode(file_get_contents($filePath), true);

                if ($content && isset($content['stats'])) {
                    $totalReports++;
                    $totalPassed += $content['stats']['passed'] ?? 0;
                    $totalFailed += $content['stats']['failed'] ?? 0;
                    $avgSuccessRate += $content['stats']['success_rate'] ?? 0;
                }
            }
        }
    }

    $avgSuccessRate = $totalReports > 0 ? round($avgSuccessRate / $totalReports, 2) : 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_reports' => $totalReports,
            'total_passed' => $totalPassed,
            'total_failed' => $totalFailed,
            'average_success_rate' => $avgSuccessRate,
            'last_updated' => @date('Y-m-d H:i:s', filemtime(REPORTS_DIR . '/latest-report.json') ?: 0),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Envía error como JSON
 */
function error($message)
{
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ]);
}

