# SISTEMA DE REPORTES DE PRUEBAS - GUÍA DE USO

## Resumen

Hemos configurado un **sistema completo de generación, almacenamiento y visualización de reportes** para las pruebas unitarias.

## Archivos creados/modificados

### Nuevos archivos:

1. **run-tests-reporter.php** - Script principal que ejecuta PHPUnit y genera reportes en JSON
2. **reports/api.php** - API para acceder a los reportes desde HTML/JavaScript
3. **reports/index.html** - Panel visual interactivo para consultar reportes (generado automáticamente)
4. **reports/README.md** - Documentación de la carpeta de reportes
5. **REPORTE_PRUEBAS.md** - Guía completa del sistema de reportes

### Archivos modificados:

1. **phpunit.xml** - Agregué configuración de cobertura
2. **run-tests.bat** - Mejorado para usar el nuevo sistema de reportes
3. **run-tests.sh** - Mejorado para usar el nuevo sistema de reportes

## Características principales

✓ Ejecución automática de PHPUnit
✓ Generación de reportes JSON con timestamp
✓ Almacenamiento de histórico de reportes
✓ Panel visual HTML con estadísticas
✓ API JSON para programadores
✓ Cálculo automático de tasa de éxito
✓ Colores y alertas visuales
✓ Historial completo y consultable

## Cómo usar

### 1. Ejecutar pruebas y generar reporte

**En Windows:**
```bash
run-tests.bat all
```

**En Linux/Mac:**
```bash
./run-tests.sh all
```

**Directamente con PHP:**
```bash
php run-tests-reporter.php
```

### 2. Ver el panel de reportes

**Opción A - Abrir en navegador:**
- Windows: `run-tests.bat reports`
- Linux/Mac: `./run-tests.sh reports`

**Opción B - URL:**
```
http://localhost/integrador-main/reports/
```

**Opción C - Archivo local:**
```
Abre: C:\xampp\htdocs\integrador-main\reports\index.html
```

## Estructura de carpeta de reportes

```
reports/
├── .gitignore              # Configuración de git
├── .htaccess              # Configuración Apache
├── README.md              # Documentación
├── api.php                # API JSON
├── index.html             # Panel visual (generado)
├── latest-report.json     # Último reporte (generado)
└── logs/                  # Histórico
    ├── report_2026-04-23_15-30-45.json
    ├── report_2026-04-23_16-45-22.json
    └── ...
```

## Información guardada en cada reporte

Cada reporte JSON contiene:

```
- Timestamp (fecha/hora de ejecución)
- Estado (EXITOSO o FALLÓ)
- Exit code de PHPUnit
- Versión de PHP usada
- Estadísticas:
  - Total de pruebas
  - Pruebas exitosas
  - Pruebas fallidas
  - Pruebas saltadas
  - Tasa de éxito (porcentaje)
- Detalles de cada prueba
```

## Dashboard Visual

El panel `reports/index.html` muestra:

- **Tarjetas por cada reporte** con:
  - Fecha y hora
  - Estado (badge verde o rojo)
  - Tasa de éxito con color:
    - Verde: >= 80%
    - Naranja: 50-79%
    - Rojo: < 50%
  - Números: exitosas, fallidas, saltadas, total
  - Botón "Ver Detalles"

## API para programadores

Acceso mediante HTTP GET:

```
/reports/api.php?action=list      # Lista todos los reportes
/reports/api.php?action=latest    # Último reporte
/reports/api.php?action=get&file=... # Reporte específico
/reports/api.php?action=stats     # Estadísticas generales
```

Respuesta en JSON:
```json
{
  "success": true,
  "reports": [...],
  "stats": {...}
}
```

## Cálculo de tasa de éxito

La tasa de éxito se calcula como:

```
Tasa = (Pruebas Exitosas / Total de Pruebas) × 100%
```

Interpretación:
- **80-100%**: Excelente - Apto para producción
- **50-79%**: Aceptable - Revisar y mejorar
- **0-49%**: Crítico - No apto para producción

## Persistencia de datos

Los reportes se guardan de forma persistente:

1. **latest-report.json** - Se sobrescribe cada ejecución (rápido acceso)
2. **logs/report_TIMESTAMP.json** - Se guarda con timestamp (histórico)

Esto permite:
- Ver el último reporte al instante
- Consultar histórico completo
- Comparar resultados en el tiempo
- Detectar tendencias de calidad

## Automatización sugerida

### Ejecutar pruebas antes de cada commit:

Crear `.git/hooks/pre-commit`:
```bash
#!/bin/bash
php run-tests-reporter.php
if [ $? -ne 0 ]; then
  echo "Las pruebas fallaron. Commit abortado."
  exit 1
fi
```

### Ejecutar cada hora (Linux/Mac):

Agregar a crontab:
```
0 * * * * cd /path/to/integrador-main && php run-tests-reporter.php
```

### En pipeline CI/CD (GitLab/GitHub):

```yaml
test:
  script:
    - php run-tests-reporter.php
  artifacts:
    paths:
      - reports/
```

## Próximos pasos

1. Ejecuta: `php run-tests-reporter.php`
2. Abre: `reports/index.html` en tu navegador
3. Verifica que los reportes se generen correctamente
4. Configura automatización si lo deseas
5. Consulta REPORTE_PRUEBAS.md para detalles técnicos

## Soporte

Archivo de documentación completa: **REPORTE_PRUEBAS.md**

Preguntas comunes:
- ¿Dónde están los reportes? → `reports/` carpeta
- ¿Cómo ver reportes antiguos? → `reports/index.html` → Ver Detalles
- ¿Cómo programar? → Usa `reports/api.php`
- ¿Cómo limpiar? → Elimina carpeta `logs/` y archivo JSON

---

Sistema listo para usar. ¡Ejecuta las pruebas y verifica los reportes!

