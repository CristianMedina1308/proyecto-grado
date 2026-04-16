# TAURO STORE - Manual de Instalación

**Versión 1.0 | Abril 2026**

---

## 1. INSTALACIÓN LOCAL

### Requisitos mínimos

- XAMPP 8.0+ (PHP 8.2, Apache, MySQL)
- Windows / Linux / Mac
- Git (opcional pero recomendado)

### Pasos

#### 1.1 Clonar o descargar el proyecto

```powershell
# Opción 1: Clonar desde Git
git clone <repositorio> C:\xampp\htdocs\integrador-main

# Opción 2: O descargar ZIP y extraer en C:\xampp\htdocs\
```

#### 1.2 Iniciar servicios en XAMPP

1. Abre **XAMPP Control Panel**
2. Inicia **Apache** y **MySQL**
3. Verifica que ambos muestren "Running" en verde

#### 1.3 Crear base de datos

```sql
-- En phpMyAdmin (http://localhost/phpmyadmin)
CREATE DATABASE tiendaropa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 1.4 Importar estructura SQL

1. Ve a phpMyAdmin → Base de datos `tiendaropa`
2. Tab **Importar** → Carga `migracion_realismo.sql`
3. Ejecuta

#### 1.5 Instalar dependencias

```powershell
cd C:\xampp\htdocs\integrador-main
composer install
```

#### 1.6 Verificar acceso

Abre navegador: `http://localhost/integrador-main`

✅ **Listo.** Deberías ver la tienda funcionando.

---

## 2. INSTALACIÓN EN PRODUCCIÓN (RAILWAY)

### Requisitos

- Cuenta en [Railway.app](https://railway.app)
- Repositorio en GitHub con este código
- Servicio MySQL provisionado en Railway

### Pasos

#### 2.1 Conectar repositorio

1. Inicia sesión en Railway
2. **New Project** → **GitHub Repo** → Selecciona tu repositorio
3. Railway detectará el `Dockerfile` automáticamente

#### 2.2 Agregar servicio MySQL

1. Dashboard → **+ New** → **MySQL**
2. Aguarda a que se provisione
3. Railway inyectará automáticamente: `MYSQLHOST`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLPORT`

#### 2.3 Configurar variables de entorno

Ve a tu servicio web en Railway → **Variables** y agrega (si es necesario):

```
# Si usas MySQL de Railway (automático), estos NO son necesarios
# Si usas DB externa, agrega:
DB_HOST=tu_host
DB_NAME=tu_db
DB_USER=tu_usuario
DB_PASS=tu_contraseña

# OPCIONAL: Para correos (si tu app lo necesita)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=tu_email@gmail.com
SMTP_PASS=tu_contraseña_app
SMTP_FROM=noreply@tauroropa.com
SMTP_SECURE=tls

# OPCIONAL: Para chatbot con OpenAI
OPENAI_API_KEY=sk-...
OPENAI_CHATBOT_MODEL=gpt-4
```

#### 2.4 Desplegar

1. Sube cambios a GitHub
2. Railway detecta push → Construye imagen Docker
3. Contenedor inicia con: `php -S 0.0.0.0:$PORT`
4. Obtén URL pública en Railway dashboard

#### 2.5 Verificar producción

- Accede a tu URL pública
- Prueba login y catálogo
- Verifica que los correos lleguen (si está configurado SMTP)

---

## 3. BASE DE DATOS

La aplicación usa MySQL. En local, si no defines variables de entorno, el proyecto cae por defecto sobre:

- `DB_HOST=127.0.0.1`
- `DB_NAME=tiendaropa`
- `DB_USER=root`
- `DB_PASS=`

En Railway, la conexión ya contempla variables administradas por la plataforma:

- `MYSQLHOST`
- `MYSQLDATABASE`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLPORT`

---

## 4. VARIABLES DE ENTORNO RELEVANTES

### Base de datos

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `MYSQLHOST`
- `MYSQLDATABASE`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLPORT`

### Correo SMTP

- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_FROM`
- `SMTP_SECURE`

### Chatbot / OpenAI

- `OPENAI_API_KEY`
- `OPENAI_CHATBOT_MODEL`
- `OPENAI_CHATBOT_REASONING`

---

## 5. VERIFICACIÓN RÁPIDA

### Local: ¿Funciona?

- [ ] Acceso a `http://localhost/integrador-main` → Carga sin errores
- [ ] Catálogo visible → Puedo navegar productos
- [ ] Carrito funciona → Puedo agregar/eliminar items
- [ ] Checkout → Puedo hacer una compra de prueba
- [ ] Admin login → Acceso a panel de administración

### Producción: ¿Funciona?

- [ ] URL pública responde
- [ ] Catálogo visible
- [ ] Conexión a BD ✅ (si hay datos, funciona)
- [ ] Correos salen (si está configurado)

---

## 6. PROBLEMAS COMUNES

| Problema | Solución |
|----------|----------|
| **"Connection refused"** | Verifica que MySQL esté running en XAMPP |
| **Error "tiendaropa database not found"** | Ejecuta paso 1.3 (crear BD) |
| **Imágenes rotas en local** | Verifica permisos en `assets/img/` |
| **En Railway: "Bad gateway"** | Espera 2-3 minutos tras deploy. Verifica logs en Railway. |
| **Correos no salen** | Valida credenciales SMTP en variables de entorno |

---

## 7. COMANDOS ÚTILES

### Local

```powershell
composer install
php tools/phpunit.phar
php -S localhost:8000
Get-Content C:\xampp\apache\logs\error.log -Tail 20
```

### Railway

```bash
railway logs --follow
railway status
git push
```

