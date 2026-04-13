@echo off
setlocal

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

if /I "%TARGET%"=="all" %PHPUNIT_CMD%
if /I "%TARGET%"=="unit" %PHPUNIT_CMD% tests\Unit
if /I "%TARGET%"=="security" %PHPUNIT_CMD% --group Security
if /I "%TARGET%"=="coverage" %PHPUNIT_CMD% --coverage-html .phpunit.cache\code-coverage
