<?php
/**
 * API de Reportes mejorada - Lee desde BD
 * Compatible con Railway y local
 *
 * Uso:
 * - GET /reports/api-db.php?action=list - Lista todos los reportes
 * - GET /reports/api-db.php?action=latest - Obtiene el último reporte
 * - GET /reports/api-db.php?action=stats - Estadísticas generales
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/conexion.php';

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

        case 'stats':
            getStats();
            break;

        case 'by-id':
            getReportById($_GET['id'] ?? 0);
            break;

        default:
            error('Acción no válida');
    }
} catch (Exception $e) {
    error($e->getMessage());
}

/**
 * Lista todos los reportes desde BD
 */
function listReports()
{
    global $conn;

    $reports = [];

    try {
        $stmt = $conn->query("
            SELECT 
                id,
                timestamp,
                status,
                total_tests,
                passed_tests,
                failed_tests,
                skipped_tests,
                success_rate,
                exit_code,
                DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
            FROM reportes_pruebas
            ORDER BY timestamp DESC
            LIMIT 50
        ");

        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error('Error al consultar BD: ' . $e->getMessage());
        return;
    }

    echo json_encode([
        'success' => true,
        'total' => count($reports),
        'source' => 'database',
        'reports' => $reports,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Obtiene el último reporte
 */
function getLatestReport()
{
    global $conn;

    try {
        $stmt = $conn->query("
            SELECT 
                id,
                timestamp,
                status,
                php_version,
                platform,
                total_tests,
                passed_tests,
                failed_tests,
                skipped_tests,
                success_rate,
                exit_code,
                 test_data
            FROM reportes_pruebas
            ORDER BY timestamp DESC
            LIMIT 1
        ");

        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            error('No hay reportes disponibles');
            return;
        }

        // Parsear test_data y extraer tests (compatible con MySQL y MariaDB)
        $tests = null;
        if (!empty($report['test_data'])) {
            $decoded = json_decode($report['test_data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $tests = $decoded['tests'] ?? null;
            }
        }

        $report['tests'] = $tests;
        unset($report['test_data']);

        echo json_encode([
            'success' => true,
            'source' => 'database',
            'report' => $report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        error('Error: ' . $e->getMessage());
    }
}

/**
 * Obtiene un reporte por ID
 */
function getReportById($id)
{
    global $conn;

    $id = (int) $id;

    if ($id <= 0) {
        error('ID inválido');
        return;
    }

    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM reportes_pruebas
            WHERE id = ?
        ");

        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            error('Reporte no encontrado');
            return;
        }

        // Parsear JSON
        if ($report['test_data']) {
            $decoded = json_decode($report['test_data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Devolver tests directamente para el panel
                $report['tests'] = $decoded['tests'] ?? null;
            }
        }

        unset($report['test_data']);

        echo json_encode([
            'success' => true,
            'report' => $report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        error('Error: ' . $e->getMessage());
    }
}

/**
 * Obtiene estadísticas generales
 */
function getStats()
{
    global $conn;

    try {
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_reports,
                SUM(passed_tests) as total_passed,
                SUM(failed_tests) as total_failed,
                AVG(success_rate) as average_success_rate,
                MAX(timestamp) as last_report_time
            FROM reportes_pruebas
        ");

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stats['average_success_rate'] = round($stats['average_success_rate'] ?? 0, 2);
        $stats['total_reports'] = (int) $stats['total_reports'];
        $stats['total_passed'] = (int) ($stats['total_passed'] ?? 0);
        $stats['total_failed'] = (int) ($stats['total_failed'] ?? 0);

        echo json_encode([
            'success' => true,
            'source' => 'database',
            'stats' => $stats,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        error('Error: ' . $e->getMessage());
    }
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

