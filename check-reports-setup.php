<?php
/**
 * Script de diagnóstico del sistema de reportes
 * Verifica que todo esté configurado correctamente
 *
 * Uso: php check-reports-setup.php
 */

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "DIAGNÓSTICO DEL SISTEMA DE REPORTES DE TAURO STORE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$checks = [];
$errors = 0;

// 1. Verificar carpeta reports
echo "1. Verificando carpeta reports/...\n";
if (is_dir('./reports')) {
    echo "   ✓ Carpeta reports/ existe\n";
    $checks['reports_dir'] = true;
} else {
    echo "   ✗ Carpeta reports/ NO existe\n";
    $checks['reports_dir'] = false;
    $errors++;
}

// 2. Verificar carpeta logs
echo "2. Verificando carpeta reports/logs/...\n";
if (is_dir('./reports/logs')) {
    echo "   ✓ Carpeta reports/logs/ existe\n";
    $checks['logs_dir'] = true;
} else {
    echo "   ⚠ Carpeta reports/logs/ se creará automáticamente\n";
    $checks['logs_dir'] = false;
}

// 3. Verificar script principal
echo "3. Verificando run-tests-reporter.php...\n";
if (file_exists('./run-tests-reporter.php')) {
    echo "   ✓ run-tests-reporter.php existe\n";
    $checks['reporter_script'] = true;
} else {
    echo "   ✗ run-tests-reporter.php NO existe\n";
    $checks['reporter_script'] = false;
    $errors++;
}

// 4. Verificar API
echo "4. Verificando reports/api.php...\n";
if (file_exists('./reports/api.php')) {
    echo "   ✓ reports/api.php existe\n";
    $checks['api_file'] = true;
} else {
    echo "   ✗ reports/api.php NO existe\n";
    $checks['api_file'] = false;
    $errors++;
}

// 5. Verificar PHPUnit
echo "5. Verificando PHPUnit...\n";
if (file_exists('./tools/phpunit.phar')) {
    echo "   ✓ PHPUnit (tools/phpunit.phar) encontrado\n";
    $checks['phpunit'] = true;
} else if (file_exists('./vendor/bin/phpunit')) {
    echo "   ✓ PHPUnit (vendor/bin/phpunit) encontrado\n";
    $checks['phpunit'] = true;
} else {
    echo "   ✗ PHPUnit NO encontrado\n";
    $checks['phpunit'] = false;
    $errors++;
}

// 6. Verificar phpunit.xml
echo "6. Verificando phpunit.xml...\n";
if (file_exists('./phpunit.xml')) {
    $content = file_get_contents('./phpunit.xml');
    if (strpos($content, 'coverage') !== false) {
        echo "   ✓ phpunit.xml existe y está configurado para reportes\n";
        $checks['phpunit_config'] = true;
    } else {
        echo "   ⚠ phpunit.xml existe pero podría necesitar actualización\n";
        $checks['phpunit_config'] = false;
    }
} else {
    echo "   ✗ phpunit.xml NO existe\n";
    $checks['phpunit_config'] = false;
    $errors++;
}

// 7. Verificar permisos de escritura
echo "7. Verificando permisos de escritura...\n";
if (is_writable('./reports')) {
    echo "   ✓ Carpeta reports/ es escribible\n";
    $checks['write_perms'] = true;
} else {
    echo "   ✗ Carpeta reports/ NO es escribible\n";
    echo "   Ejecuta: chmod 755 reports/\n";
    $checks['write_perms'] = false;
    $errors++;
}

// 8. Verificar tests
echo "8. Verificando carpeta tests/...\n";
if (is_dir('./tests/Unit')) {
    $testFiles = glob('./tests/Unit/*.php');
    $count = count($testFiles);
    echo "   ✓ tests/Unit/ existe con {$count} archivos de prueba\n";
    $checks['tests_exist'] = true;
} else {
    echo "   ✗ tests/Unit/ NO existe\n";
    $checks['tests_exist'] = false;
    $errors++;
}

// 9. Verificar PHP version
echo "9. Verificando versión de PHP...\n";
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "   ✓ PHP {$phpVersion} (compatible)\n";
    $checks['php_version'] = true;
} else {
    echo "   ✗ PHP {$phpVersion} (se requiere 7.4.0 o superior)\n";
    $checks['php_version'] = false;
    $errors++;
}

// 10. Verificar documentación
echo "10. Verificando documentación...\n";
$docs = [
    'TESTING.md' => 'Guía completa de pruebas',
    'START_HERE_REPORTES.txt' => 'Instrucciones'
];

$doc_count = 0;
foreach ($docs as $file => $desc) {
    if (file_exists("./{$file}")) {
        $doc_count++;
    }
}

echo "   ✓ Encontrados {$doc_count}/" . count($docs) . " archivos de documentación\n";
$checks['documentation'] = $doc_count === count($docs);

// Resumen
echo "\n═══════════════════════════════════════════════════════════════\n";
echo "RESUMEN\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$passed = count(array_filter($checks, function($v) { return $v === true; }));
$total = count($checks);

echo "Verificaciones pasadas: {$passed}/{$total}\n";

if ($errors > 0) {
    echo "\n⚠️  ERRORES ENCONTRADOS: {$errors}\n";
    echo "\nErrores críticos que requieren atención:\n";
    foreach ($checks as $check => $result) {
        if ($result === false) {
            echo "  • {$check}\n";
        }
    }
    echo "\nRevisa los archivos de documentación para resolver.\n";
} else {
    echo "\n✅ ¡TODOS LOS VERIFICACIONES PASARON!\n";
    echo "\nEl sistema está listo para usar. Ejecuta:\n";
    echo "  php run-tests-reporter.php\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

exit($errors > 0 ? 1 : 0);

