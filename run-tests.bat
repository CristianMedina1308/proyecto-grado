@echo off
setlocal EnableDelayedExpansion

set TARGET=%1
if "%TARGET%"=="" set TARGET=all

set PHPUNIT_CMD=
set PHP_BIN=php

if exist C:\xampp\php\php.exe set PHP_BIN=C:\xampp\php\php.exe

if exist vendor\bin\phpunit.bat set PHPUNIT_CMD=vendor\bin\phpunit.bat
if "%PHPUNIT_CMD%"=="" if exist tools\phpunit.phar set PHPUNIT_CMD=%PHP_BIN% tools\phpunit.phar

if "%PHPUNIT_CMD%"=="" (
  echo PHPUnit no esta disponible. Instala dependencias con Composer o agrega tools\phpunit.phar.
  exit /b 1
)

REM Ejecutar con el nuevo sistema de reportes
echo.
echo ========================================
echo TAURO STORE - TEST RUNNER
echo ========================================
echo.

if /I "%TARGET%"=="all" (
  echo Ejecutando todas las pruebas con reportes...
  %PHP_BIN% run-tests-reporter.php
) else if /I "%TARGET%"=="unit" (
  echo Ejecutando pruebas unitarias con reportes...
  %PHP_BIN% run-tests-reporter.php
) else if /I "%TARGET%"=="coverage" (
  echo Ejecutando con reporte de cobertura...
  %PHPUNIT_CMD% --coverage-html .phpunit.cache\code-coverage
) else if /I "%TARGET%"=="reports" (
  echo Abriendo panel de reportes...
  start reports\index.html
) else (
  echo Uso: run-tests.bat [all^|unit^|coverage^|reports]
  echo.
  echo Opciones:
  echo   all      - Ejecutar todas las pruebas y generar reporte
  echo   unit     - Ejecutar pruebas unitarias
  echo   coverage - Generar reporte de cobertura
  echo   reports  - Abrir panel de reportes en navegador
  exit /b 1
)

endlocal
