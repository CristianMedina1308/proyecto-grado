# Sistema de Reportes de Pruebas Unitarias

Tauro Store cuenta con un sistema completo de generación, almacenamiento y visualización de reportes de pruebas unitarias.

## Características

- Ejecución automática de pruebas con PHPUnit
- Generación de reportes en JSON con timestamp
- Almacenamiento de histórico de todos los reportes
- Panel visual HTML para consultar resultados
- Estadísticas detalladas de cada ejecución
- Tasa de éxito y métricas de calidad

## Uso

### Ejecutar pruebas y generar reportes

**En Windows:**
```bash
run-tests.bat all
```

**En Linux/Mac:**
```bash
./run-tests.sh all
```

O directamente con PHP:
```bash
php run-tests-reporter.php
```

### Ver el panel de reportes

**En Windows:**
```bash
run-tests.bat reports
```

**En Linux/Mac:**
```bash
./run-tests.sh reports
```

O abre el archivo `reports/index.html` en tu navegador:
```
http://localhost/integrador-main/reports/
```

## Estructura de archivos

```
reports/
├── index.html                    # Panel visual de reportes
├── latest-report.json           # Último reporte generado
└── logs/
    ├── report_2026-04-23_15-30-45.json  # Reporte archivado 1
    ├── report_2026-04-23_16-45-22.json  # Reporte archivado 2
    └── ...                              # Más reportes
```

## Información en cada reporte

Cada reporte JSON contiene:

```json
{
  "timestamp": "2026-04-23 15:30:45",
  "timestamp_unix": 1713897045,
  "exit_code": 0,
  "status": "EXITOSO",
  "php_version": "8.2.0",
  "platform": "Linux ...",
  "stats": {
    "total": 10,
    "passed": 8,
    "failed": 2,
    "skipped": 0,
    "success_rate": 80
  },
  "tests": {
    "tests": [
      {
        "name": "AppFunctionsTest::testValidateEmail",
        "status": "pass"
      },
      ...
    ]
  }
}
```

## Panel Visual

El archivo `reports/index.html` proporciona:

- Listado de todos los reportes generados
- Tarjeta para cada reporte mostrando:
  - Fecha y hora de ejecución
  - Estado (EXITOSO/FALLÓ)
  - Tasa de éxito en porcentaje
  - Número de pruebas: exitosas, fallidas, saltadas
  - Botón para ver detalles completos
- Código de colores:
  - Verde: Tasa de éxito >= 80%
  - Naranja: Tasa de éxito entre 50% y 80%
  - Rojo: Tasa de éxito < 50%

## Automatización

Para ejecutar pruebas automáticamente cada vez que cambies código:

### Usando un script de vigilancia (Linux/Mac)

```bash
#!/bin/bash
while true; do
  php run-tests-reporter.php
  sleep 60  # Esperar 60 segundos antes de ejecutar de nuevo
done
```

### Usando un cron job (Linux/Mac)

```bash
# Ejecutar pruebas cada hora
0 * * * * cd /path/to/integrador-main && php run-tests-reporter.php
```

### Usar como pre-commit hook

Crea un archivo `.git/hooks/pre-commit`:

```bash
#!/bin/bash
php run-tests-reporter.php
if [ $? -ne 0 ]; then
  echo "Las pruebas fallaron. Commit abortado."
  exit 1
fi
```

Haz ejecutable:
```bash
chmod +x .git/hooks/pre-commit
```

## Configuración de PHPUnit

El archivo `phpunit.xml` está configurado para:

- Ejecutar pruebas desde `tests/Unit`
- Generar reportes JSON
- Generar cobertura HTML
- Fallar en caso de warnings
- Modo verbose

Para modificar la configuración, edita `phpunit.xml`.

## Interpretación de resultados

### Status "EXITOSO" (exit code 0)
- Todas las pruebas pasaron
- No hay errores críticos
- Calidad de código aceptable

### Status "FALLÓ" (exit code ≠ 0)
- Una o más pruebas fallaron
- Requiere atención antes de deploy
- Revisar detalles en el reporte

### Tasa de éxito

- **80-100%**: Excelente. Código listo para producción
- **50-79%**: Aceptable. Se pueden mejorar algunos aspectos
- **0-49%**: Crítico. No apto para producción

## Ejemplo: Ejecutar y ver reportes

```bash
# 1. Ejecutar pruebas
php run-tests-reporter.php

# 2. Ver último reporte en JSON
cat reports/latest-report.json | python -m json.tool

# 3. Abrir panel visual (Windows)
start reports\index.html

# 4. Abrir panel visual (Linux/Mac)
open reports/index.html
```

## Troubleshooting

**Error: "PHPUnit no esta disponible"**
- Asegúrate de que `tools/phpunit.phar` existe
- O instala con Composer: `composer install`

**Reportes no se generan**
- Verifica permisos en la carpeta `reports/`
- Asegúrate que PHP tiene permisos de escritura

**Panel HTML no se ve correctamente**
- Limpia cache del navegador (Ctrl+F5)
- Intenta en otro navegador
- Revisa la consola del navegador para errores

## Archivos relacionados

- `phpunit.xml` - Configuración de PHPUnit
- `run-tests-reporter.php` - Script PHP de ejecución y reporte
- `run-tests.bat` - Script Windows mejorado
- `run-tests.sh` - Script Linux/Mac mejorado
- `reports/` - Carpeta de almacenamiento de reportes
- `tests/` - Directorio con las pruebas unitarias

## Próximos pasos

1. Ejecuta: `php run-tests-reporter.php`
2. Abre: `reports/index.html`
3. Revisa los resultados
4. Si hay fallos, analiza el log y corrige
5. Vuelve a ejecutar para confirmar

¡Gracias por mantener la calidad de Tauro Store!

