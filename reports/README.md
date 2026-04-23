# Reportes de Pruebas Unitarias

Esta carpeta contiene todos los reportes de pruebas unitarias generados por PHPUnit.

## Archivos

- `index.html` - Panel visual interactivo para ver todos los reportes
- `api.php` - API JSON para acceder a los reportes programáticamente
- `latest-report.json` - Último reporte generado (se sobrescribe en cada ejecución)
- `logs/` - Carpeta con histórico de todos los reportes archivados

## Acceso

### Panel Visual
Abre en tu navegador:
```
http://localhost/integrador-main/reports/
```

### API JSON

**Listar todos los reportes:**
```
http://localhost/integrador-main/reports/api.php?action=list
```

**Obtener último reporte:**
```
http://localhost/integrador-main/reports/api.php?action=latest
```

**Obtener reporte específico:**
```
http://localhost/integrador-main/reports/api.php?action=get&file=report_2026-04-23_15-30-45.json
```

**Obtener estadísticas:**
```
http://localhost/integrador-main/reports/api.php?action=stats
```

## Generación

Los reportes se generan automáticamente cuando ejecutas:

**Windows:**
```bash
run-tests.bat all
```

**Linux/Mac:**
```bash
./run-tests.sh all
```

**Directamente:**
```bash
php run-tests-reporter.php
```

## Estructura de un Reporte

```json
{
  "timestamp": "2026-04-23 15:30:45",
  "timestamp_unix": 1713897045,
  "exit_code": 0,
  "status": "EXITOSO",
  "php_version": "8.2.0",
  "stats": {
    "total": 25,
    "passed": 24,
    "failed": 1,
    "skipped": 0,
    "success_rate": 96
  },
  "tests": {
    "tests": [...]
  }
}
```

## Limpieza

Para limpiar todos los reportes antiguos:

**Windows:**
```batch
rmdir /s /q logs
del latest-report.json
```

**Linux/Mac:**
```bash
rm -rf logs
rm latest-report.json
```

## Integración CI/CD

Para integrar con un pipeline CI/CD:

```yaml
test:
  script:
    - php run-tests-reporter.php
  artifacts:
    paths:
      - reports/
    reports:
      junit: reports/latest-report.json
```

---

Generado automáticamente por el sistema de pruebas de Tauro Store.

