# SOLUCIÓN PARA RAILWAY - Paso a Paso

## Problema Original

✗ Intentas acceder a `localhost/integrador-main/reports/` en Railway
✗ Los archivos JSON se pierden en cada deploy
✗ No puedes guardar reportes de forma persistente

## Solución

Usar la **Base de Datos** para guardar reportes (que persisten en Railway).

---

## PASO 1: Crear la Tabla en tu BD

### 1.1 En Local (primera vez)

Abre MySQL en tu local:

```bash
mysql -u root -p
```

Dentro de MySQL:

```sql
-- Selecciona tu BD
USE integrador;

-- Copia y pega TODO esto:
CREATE TABLE IF NOT EXISTS reportes_pruebas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL COMMENT 'EXITOSO o FALLÓ',
    php_version VARCHAR(20),
    platform VARCHAR(100),
    total_tests INT DEFAULT 0,
    passed_tests INT DEFAULT 0,
    failed_tests INT DEFAULT 1,
    skipped_tests INT DEFAULT 0,
    success_rate DECIMAL(5, 2) DEFAULT 0,
    test_data LONGTEXT COMMENT 'JSON con detalles de todas las pruebas',
    exit_code INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp DESC),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_ultimo_reporte AS
SELECT *
FROM reportes_pruebas
ORDER BY timestamp DESC
LIMIT 1;
```

Presiona Enter. ¡Listo!

### 1.2 En Railway (producción)

**Opción A - Automático (mejor):**

Si tienes un script que ejecuta antes de deployar:

```bash
# Agregar en tu pipeline
mysql -h $DATABASE_URL_HOST -u $DATABASE_URL_USER -p$DATABASE_URL_PASSWORD -D $DATABASE_URL_NAME < migrations/001_crear_tabla_reportes.sql
```

**Opción B - Manual:**

1. Ve a https://railway.app
2. Abre tu proyecto
3. Selecciona "MySQL"
4. Haz clic en "Connect"
5. Ve a "Query" o "SQL Query"
6. Copia y pega el SQL de arriba
7. Ejecuta

---

## PASO 2: Usar el Nuevo Script

En lugar de:
```bash
php run-tests-reporter.php
```

Usa:
```bash
php run-tests-reporter-db.php
```

Este script nuevo:
- ✅ Guarda en **BD** (persiste)
- ✅ También guarda en archivos JSON (para respaldo)
- ✅ Funciona en local y en Railway

---

## PASO 3: Acceder a los Reportes

### En Local:

```
http://localhost/integrador-main/reports/index-db.html
```

O por API:

```
http://localhost/integrador-main/reports/api-db.php?action=latest
```

### En Railway:

```
https://proyecto-grado-production-18cd.up.railway.app/reports/index-db.html
```

O por API:

```
https://proyecto-grado-production-18cd.up.railway.app/reports/api-db.php?action=latest
```

---

## PASO 4: Workflow Completo

### Localmente (mientras desarrollas):

```bash
# 1. Haces cambios
vim archivo.php

# 2. Ejecutas pruebas
php run-tests-reporter-db.php

# 3. Ves resultados
# Abre: http://localhost/integrador-main/reports/index-db.html

# 4. Si todo bien, haces commit
git add .
git commit -m "Cambios completados"
git push
```

### En Railway (automático):

```
1. GitHub recibe push
2. Railway se dispara automáticamente
3. Se crea contenedor nuevo (los archivos JSON se pierden, pero NO IMPORTA)
4. Tu BD sigue ahí con los reportes anteriores
5. Accedes a: https://tu-dominio.railway.app/reports/index-db.html
```

---

## PASO 5: Ejecutar Pruebas en Railway

### Opción A: En SSH

```bash
# Conectarse a Railway
railway shell

# Una vez conectado, ejecuta
php run-tests-reporter-db.php

# Cierra sesión
exit
```

### Opción B: En CI/CD

Agregar a tu `.github/workflows/deploy.yml`:

```yaml
name: Test and Deploy

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Run Tests
        run: php run-tests-reporter-db.php
      - name: Deploy to Railway
        env:
          RAILWAY_TOKEN: ${{ secrets.RAILWAY_TOKEN }}
        run: |
          # Deploy command aquí
```

---

## ARCHIVOS CREADOS/MODIFICADOS

Nuevos archivos:
- ✅ `run-tests-reporter-db.php` - Script que usa BD
- ✅ `reports/api-db.php` - API que lee desde BD
- ✅ `reports/index-db.html` - Panel visual (auto-generado)
- ✅ `migrations/001_crear_tabla_reportes.sql` - Migración SQL

---

## URLs para Recordar

**Local:**
```
http://localhost/integrador-main/reports/index-db.html
http://localhost/integrador-main/reports/api-db.php?action=stats
```

**Railway:**
```
https://proyecto-grado-production-18cd.up.railway.app/reports/index-db.html
https://proyecto-grado-production-18cd.up.railway.app/reports/api-db.php?action=stats
```

---

## Datos Guardados

Cada ejecución de pruebas guarda:
- Timestamp (fecha/hora)
- Status (EXITOSO/FALLÓ)
- PHP version
- Total tests, passed, failed, skipped
- Success rate (%)
- Detalles de cada prueba en JSON
- Exit code

Todo esto en la tabla `reportes_pruebas` de tu BD.

---

## API Endpoints

### Listar todos (últimos 50)
```
/reports/api-db.php?action=list
```

Respuesta:
```json
{
  "success": true,
  "total": 5,
  "source": "database",
  "reports": [...]
}
```

### Último reporte
```
/reports/api-db.php?action=latest
```

### Estadísticas generales
```
/reports/api-db.php?action=stats
```

Respuesta:
```json
{
  "total_reports": 25,
  "total_passed": 240,
  "total_failed": 10,
  "average_success_rate": 95.5,
  "last_report_time": "2026-04-23 22:15:30"
}
```

---

## Ventajas del Nuevo Sistema

✅ **Persistencia en Railway** - Los reportes no se pierden entre deploys
✅ **Base de Datos** - Histórico completo e ilimitado
✅ **API Programática** - Acceso desde código
✅ **Panel Visual** - Ver resultados en HTML
✅ **Compatible Local** - Funciona exactamente igual en local
✅ **URL Pública** - Usa tu dominio de Railway
✅ **Sin Commits** - No necesitas commitear archivos JSON cada vez

---

## Troubleshooting

**P: ¿Dónde está mi BD de Railway?**

R: La encontrarás en Railway Console > Tu Proyecto > MySQL > Variables de Entorno:
- DATABASE_URL (conexión completa)
- DATABASE_URL_HOST
- DATABASE_URL_USER
- DATABASE_URL_PASSWORD
- DATABASE_URL_DB

**P: ¿Cómo veo la tabla creada?**

R: Conéctate a tu BD:
```bash
mysql -h $DATABASE_URL_HOST -u $DATABASE_URL_USER -p$DATABASE_URL_PASSWORD -D $DATABASE_URL_DB
```

Dentro de MySQL:
```sql
SHOW TABLES;
DESC reportes_pruebas;
SELECT * FROM reportes_pruebas;
```

**P: ¿Los archivos JSON siguen siendo necesarios?**

R: No, pero se crean como respaldo. Puedes ignorarlos.

**P: ¿Cómo limpiar reportes antiguos?**

R: En MySQL:
```sql
DELETE FROM reportes_pruebas WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

## Checklist Final

- [ ] Creé la tabla `reportes_pruebas` en local
- [ ] Creé la tabla en Railway
- [ ] Cambié a usar `run-tests-reporter-db.php`
- [ ] Ejecuté pruebas en local: `php run-tests-reporter-db.php`
- [ ] Accedí a `http://localhost/integrador-main/reports/index-db.html`
- [ ] Hice push a GitHub
- [ ] Railway se deployó
- [ ] Accedí a `https://tu-dominio.railway.app/reports/index-db.html`
- [ ] ¡Veo los reportes!

---

## Próximo Paso

Ejecuta esto ahora:

```bash
php run-tests-reporter-db.php
```

Luego abre:

```
http://localhost/integrador-main/reports/index-db.html
```

¡Deberías ver tu primer reporte guardado en BD! 🎉

---

**Problema resuelto:** Ahora tienes reportes persistentes en Railway con tu dominio real.

