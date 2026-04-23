#!/usr/bin/env sh
# Script mejorado para ejecutar pruebas unitarias con reportes
# Uso: ./run-tests.sh [all|unit|coverage|reports]

set -eu

target="${1:-all}"
phpunit_cmd=""
php_bin="php"

# Detectar PHP
if command -v php >/dev/null 2>&1; then
  php_bin="php"
fi

# Detectar PHPUnit
if [ -x vendor/bin/phpunit ]; then
  phpunit_cmd="vendor/bin/phpunit"
elif [ -f tools/phpunit.phar ]; then
  phpunit_cmd="php tools/phpunit.phar"
else
  echo "PHPUnit no esta disponible. Instala dependencias con Composer o agrega tools/phpunit.phar."
  exit 1
fi

echo ""
echo "========================================"
echo "TAURO STORE - TEST RUNNER"
echo "========================================"
echo ""

case "$target" in
  all)
    echo "Ejecutando todas las pruebas con reportes..."
    sh -c "$php_bin run-tests-reporter.php"
    ;;
  unit)
    echo "Ejecutando pruebas unitarias con reportes..."
    sh -c "$php_bin run-tests-reporter.php"
    ;;
  coverage)
    echo "Ejecutando con reporte de cobertura..."
    sh -c "$phpunit_cmd --coverage-html .phpunit.cache/code-coverage"
    ;;
  reports)
    echo "Abriendo panel de reportes..."
    if command -v xdg-open >/dev/null 2>&1; then
      xdg-open reports/index.html
    elif command -v open >/dev/null 2>&1; then
      open reports/index.html
    else
      echo "Abre manualmente: reports/index.html"
    fi
    ;;
  *)
    echo "Uso: ./run-tests.sh [all|unit|coverage|reports]"
    echo ""
    echo "Opciones:"
    echo "  all      - Ejecutar todas las pruebas y generar reporte"
    echo "  unit     - Ejecutar pruebas unitarias"
    echo "  coverage - Generar reporte de cobertura"
    echo "  reports  - Abrir panel de reportes en navegador"
    exit 1
    ;;
esac
