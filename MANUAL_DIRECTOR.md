# TAURO STORE - Manual de Director

**Versión 1.0 | Abril 2026**

---

## Resumen Ejecutivo

**Tauro Store** es una aplicación e-commerce completa para venta de ropa y accesorios. Stack: PHP 8.2, MySQL, JavaScript vanilla.

- Catálogo de productos con búsqueda
- Carrito y checkout con validaciones
- Sistema de facturación PDF
- Panel administrativo completo
- Chatbot de soporte y consultas
- Pruebas unitarias incluidas

---

## Tecnologías usadas

### Backend

- PHP 8.2+
- PDO para acceso a MySQL
- MySQL / MariaDB
- PHPMailer para envío de correos
- FPDF para generación de facturas PDF

### Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap

### Testing

- PHPUnit 11
- Suite unitaria propia del proyecto en `tests/`

### Infraestructura

- XAMPP para entorno local en Windows
- Docker para empaquetado
- Railway para despliegue del contenedor

---

## Estructura general del proyecto

```text
integrador-main/
├── admin/                        # Zona administrativa: usuarios, pedidos, productos
├── assets/                       # Estilos, scripts, imágenes del sitio
├── includes/
│   ├── app.php                   # Motor principal (sesión, CSRF, flashes, utilidades)
│   ├── conexion.php              # Conexión a la base de datos
│   ├── pedidos_utils.php         # Lógica para gestión de pedidos y envíos
│   ├── chatbot_utils.php         # Chatbot conversacional
│   └── business_rules.php        # Reglas de negocio sin dependencias de BD
├── tests/                        # Pruebas unitarias con PHPUnit
├── Dockerfile                    # Configuración para despliegue
├── composer.json                 # Dependencias (PHPMailer, PHPUnit, etc.)
└── README.md                     # Documentación principal
```

---

## Diagrama de despliegue

```mermaid
flowchart TD
    U[Usuario / Navegador] --> R[Railway Router]
    R --> W[Servicio Web Tauro Store<br/>Contenedor Docker con PHP 8.2]
    W --> A[Aplicacion PHP<br/>index.php, checkout.php, admin/, chatbot_api.php]
    A --> DB[(MySQL Railway o Externo)]
    A --> FS[Archivos locales del proyecto<br/>assets/, includes/, imagenes]
    A --> SMTP[Servidor SMTP]
    A --> OAI[API OpenAI]
```

---

## Arquitectura funcional simplificada

```mermaid
flowchart LR
    UI[Frontend PHP + JS] --> CORE[includes/app.php]
    UI --> PED[includes/pedidos_utils.php]
    UI --> CHAT[includes/chatbot_utils.php]
    PED --> DB[(MySQL)]
    CHAT --> DB
    UI --> PDF[FPDF]
    UI --> MAIL[PHPMailer]
```

---

## Seguridad aplicada en el proyecto

- Tokens CSRF para todas las acciones sensibles.
- Validación de sesión antes de operaciones administrativas.
- Preparación de consultas SQL con PDO.
- Normalización de entrada de usuarios.
- Uso de `basename()` para rutas de archivos.
- Tokens públicos para consultas limitadas de facturas.
- Validaciones de cantidad, tallas, estados y datos del carrito.

---

## Estado actual del proyecto

El sistema está preparado para:

- ejecución local con XAMPP,
- despliegue en Railway mediante Docker,
- conexión a MySQL por variables de entorno,
- ejecución de pruebas unitarias.

