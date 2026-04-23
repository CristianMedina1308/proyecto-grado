# Sistema de Reportes para Railway

## Problema Identificado

Cuando desployas a Railway:
- Los archivos JSON se pierden en cada deploy (contenedor nuevo)
- `localhost` no funciona en Railroad (debe usar dominio)
- Necesitas persistencia de datos

## Solución Implementada

He creado un sistema que usa **la base de datos** para guardar reportes (ahora persisten entre deploys).

---

## Paso 1: Ejecutar la Migración SQL

### En tu BD local (para testing):

```bash
# Conectarse a MySQL
mysql -u root -p

# Seleccionar tu BD
USE tu_base_de_datos;

# Ejecutar el SQL de la migración
SOURCE migrations/001_crear_tabla_reportes.sql;
```

### En Railway (Automático o Manual):

**Opción A - Automático (Recomendado):**

Si tienes un script de deploy que ejecuta migraciones, agrega:

```bash
# En tu pipeline de deploy
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME < migrations/001_crear_tabla_reportes.sql
```

**Opción B - Manual:**

1. Ve a Railway Dashboard
2. Selecciona tu proyecto
3. Abre la BD MySQL
4. Va a "SQL" o "Connect"
5. Copia y pega el contenido de `migrations/001_crear_tabla_reportes.sql`
6. Ejecuta

---

## Paso 2: Usar el Nuevo Script

Ahora tienes dos opciones:

### Opción A: Script NUEVO (Recomendado - Usa BD)

```bash
# Local
php run-tests-reporter-db.php

# En Railway (dentro del servidor)
php run-tests-reporter-db.php
```

**Ventajas:**
- ✅ Persiste en BD (sobrevive deploys)
- ✅ Funciona en Railway
- ✅ Funciona en local
- ✅ Sin necesidad de commitear archivos

### Opción B: Script ANTIGUO (Solo archivos JSON)

```bash
php run-tests-reporter.php
```

**Desventajas:**
- ❌ Los archivos se pierden en Railway
- ❌ Solo funciona en local

---

## Paso 3: Acceder a los Reportes

### En Local:

```
http://localhost/integrador-main/reports/index-db.html
```

### En Railway:

```
https://proyecto-grado-production-18cd.up.railway.app/reports/index-db.html
```

O accede a la API directamente:

```
https://proyecto-grado-production-18cd.up.railway.app/reports/api-db.php?action=latest
```

---

## API Endpoints

### Listar reportes
```
GET /reports/api-db.php?action=list
```

### Obtener último
```
GET /reports/api-db.php?action=latest
```

### Estadísticas
```
GET /reports/api-db.php?action=stats
```

### Por ID
```
GET /reports/api-db.php?action=by-id&id=123
```

---

## Ejemplo Completo en Railway

### 1. Haces cambios locales

```bash
# Tu código
echo "cambios" > archivo.php
```

### 2. Haces commit a GitHub

```bash
git add .
git commit -m "Nuevos cambios"
git push origin main
```

### 3. Railway despliega automáticamente

- Se crea un nuevo contenedor
- Se instalan dependencias
- Los archivos JSON se pierden (pero ahora no importa)

### 4. Ejecutas pruebas EN RAILWAY

Opción A: Agregar a tu Dockerfile/startup script:

```dockerfile
# En tu Dockerfile
CMD ["sh", "-c", "php run-tests-reporter-db.php && php -S 0.0.0.0:8080"]
```

Opción B: Manual (vi  SSH a Railway):

```bash
# Conectarse a Railway
railway shell

# Ejecutar pruebas
php run-tests-reporter-db.php

# Ver reportes
curl https://tu-dominio.railway.app/reports/api-db.php?action=latest
```

### 5. Ver resultados

```
https://proyecto-grado-production-18cd.up.railway.app/reports/index-db.html
```

Los reportes están en la BD 📊 ¡Persisten entre deploys!

---

## Estructura de datos guardada

En la tabla `reportes_pruebas`:

```sql
id: 1
timestamp: 2026-04-23 21:30:45
status: EXITOSO
php_version: 8.5.3
platform: Linux ...
total_tests: 25
passed_tests: 24
failed_tests: 1
skipped_tests: 0
success_rate: 96.00
test_data: {JSON con detalles}
exit_code: 0
created_at: 2026-04-23 21:30:45
```

---

## Workflow recomendado

### Local:

1. Haces cambios
2. `php run-tests-reporter-db.php` localmente
3. Ves reportes en `http://localhost/integrador-main/reports/index-db.html`
4. Si pasan, haces commit

### Railway:

1. Se hace deploy automático
2. Ejecutas pruebas (en SSH o CD/CI)
3. Los reportes se guardan en BD
4. Accedes a `https://tu-dominio.railway.app/reports/index-db.html`
5. Verific los resultados sin perder nada entre deploys

---

## Ventajas

✅ Los reportes **persisten** entre deploys (están en BD)
✅ Funciona en **Railway** con tu dominio real
✅ Funciona en **Local** exactamente igual
✅ **Histórico completo** de todas las pruebas
✅ **API programática** para integración
✅ **Panel visual** actualizado
✅ **Sin necesidad** de commitear archivos
✅ **Escalable** a cientos de reportes

---

## Troubleshooting

**P: ¿Cómo me conecto a la BD de Railway desde local?**

R: Usa los datos de conexión en las variables de entorno de Railway:

```bash
# En tu .env local
DB_HOST=tu-host-railway.railways.internal
DB_USER=root
DB_PASS=password
DB_NAME=railway
```

**P: ¿Se pierden reportes entre deploys?**

R: ¡NO! Están en BD, no en archivos.

**P: ¿Cómo ver histórico de reportes?**

R: En el panel visual `index-db.html` verás todos los reportes guardados.

**P: ¿Puedo exportar los reportes?**

R: Sí, desde la API:

```bash
curl https://tu-dominio.railway.app/reports/api-db.php?action=list > reportes.json
```

---

## URLs correctas

### Railway (Producción)

```
https://proyecto-grado-production-18cd.up.railway.app/reports/index-db.html
https://proyecto-grado-production-18cd.up.railway.app/reports/api-db.php?action=latest
```

### Local (Desarrollo)

```
http://localhost/integrador-main/reports/index-db.html
http://localhost/integrador-main/reports/api-db.php?action=latest
```

---

## Próximos pasos

1. ✅ Ejecuta la migración SQL en tu BD
2. ✅ Usa `run-tests-reporter-db.php` en lugar del viejo
3. ✅ Accede a `index-db.html` con tu URL de Railway
4. ✅ ¡Los reportes se guardan en BD y persisten!

---

**Problema original resuelto:** Ahora los reportes se guardan en la BD, persisten en Railway, y puedes acceder con tu dominio real sin perder datos entre deploys.

Sistema compatible con Railway ✅

