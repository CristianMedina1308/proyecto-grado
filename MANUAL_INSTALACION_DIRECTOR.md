# TAURO STORE - Manual de Instalación

**Versión 1.0 | Abril 2026**

---

## Resumen Ejecutivo

**Tauro Store** es una aplicación e-commerce completa para venta de ropa y accesorios. Stack: PHP 8.2, MySQL, JavaScript vanilla.

- ✅ Catálogo de productos
- ✅ Carrito y checkout con validaciones
- ✅ Sistema de facturación PDF
- ✅ Panel administrativo
- ✅ Chatbot de soporte
- ✅ Pruebas unitarias incluidas

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
CREATE DATABASE maquillaje CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 1.4 Importar estructura SQL
1. Ve a phpMyAdmin → Base de datos `maquillaje`
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

## 3. ESTRUCTURA CRÍTICA DEL PROYECTO

```
integrador-main/
├── index.php                    # Inicio
├── productos.php                # Catálogo
├── producto.php                 # Detalle de producto
├── checkout.php                 # Carrito y compra
├── admin/                        # Panel administrativo (usuarios, pedidos, productos)
├── includes/
│   ├── app.php                  # Core (sesión, CSRF, flashes)
│   ├── conexion.php             # Conexión BD
│   ├── pedidos_utils.php        # Lógica de pedidos y envíos
│   ├── chatbot_utils.php        # Chatbot
│   └── business_rules.php       # Reglas de negocio puras
├── assets/                       # CSS, JS, imágenes
├── tests/                        # Pruebas unitarias PHPUnit
├── Dockerfile                    # Para despliegue en Railway
├── composer.json                 # Dependencias (PHPMailer, PHPUnit)
└── README.md                     # Documentación técnica completa
```

---

## 4. VERIFICACIÓN RÁPIDA

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

## 5. PROBLEMAS COMUNES

| Problema | Solución |
|----------|----------|
| **"Connection refused"** | Verifica que MySQL esté running en XAMPP |
| **Error "maquillaje database not found"** | Ejecuta paso 1.3 (crear BD) |
| **Imágenes rotas en local** | Verifica permisos en `assets/img/` |
| **En Railway: "Bad gateway"** | Espera 2-3 minutos tras deploy. Verifica logs en Railway. |
| **Correos no salen** | Valida credenciales SMTP en variables de entorno |

---

## 6. COMANDOS ÚTILES

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

---

## 7. RESULTADOS DE PRUEBAS UNITARIAS

**Estado:** ✅ Todas las pruebas pasadas

| Módulo | Pruebas | Estado |
|--------|---------|--------|
| Autenticación (REQ-001) | 8 tests | ✅ PASS |
| CSRF (REQ-002) | 7 tests | ✅ PASS |
| Validación de entrada (REQ-003) | 12 tests | ✅ PASS |
| Carrito (REQ-004) | 13 tests | ✅ PASS |
| Estados de pedido (REQ-005) | 13 tests | ✅ PASS |

**Total:** 53 pruebas | Exitosas: 53 | Fallidas: 0

**Cobertura:**
- `includes/app.php` - Sesión, CSRF, flashes, borrado de imágenes
- `includes/pedidos_utils.php` - Estados, transiciones, normalización
- `includes/chatbot_utils.php` - Formateo, extracción de referencias
- `includes/business_rules.php` - Carrito, cantidades, impuestos, tallas

---

## 8. SEGURIDAD

✅ Tokens CSRF
✅ PDO para queries
✅ Validación de entrada
✅ Sesiones encriptadas
✅ Token público para facturas

---

**Tauro Store Manual de Instalación**
**Abril 2026**

