#!/usr/bin/env sh
set -eu

target="${1:-all}"
phpunit_cmd=""

if [ -x vendor/bin/phpunit ]; then
  phpunit_cmd="vendor/bin/phpunit"
elif [ -f tools/phpunit.phar ]; then
  phpunit_cmd="php tools/phpunit.phar"
else
  echo "PHPUnit no esta disponible. Instala dependencias con Composer o agrega tools/phpunit.phar."
  exit 1
fi

case "$target" in
  all) sh -c "$phpunit_cmd" ;;
  unit) sh -c "$phpunit_cmd tests/Unit" ;;
  security) sh -c "$phpunit_cmd --group Security" ;;
  coverage) sh -c "$phpunit_cmd --coverage-html .phpunit.cache/code-coverage" ;;
  *) echo "Uso: ./run-tests.sh [all|unit|security|coverage]"; exit 1 ;;
esac
