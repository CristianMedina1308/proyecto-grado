# Tauro Store - Manual de Usuario

**Versión 1.0 | Abril 2026**

---

## Descripción general

**Tauro Store** es una aplicación web e-commerce para venta de ropa y accesorios masculinos.

---

## Funcionalidades disponibles

- Registro, login y cierre de sesión.
- Gestión de sesión de usuario.
- Catálogo de productos.
- Favoritos.
- Carrito persistido del lado del cliente.
- Checkout con validaciones y CSRF.
- Cálculo de envío por ciudad y zona.
- Generación de pedido y detalle de pedido.
- Factura PDF y factura pública verificable por token.
- Historial y transición de estados de pedidos.
- Panel administrativo para productos, pedidos y usuarios.
- Chatbot con respuestas de apoyo y consulta pública de pedidos por token.

---

## Módulos del sistema

### Catálogo y productos

- Inicio: `index.php`
- Catálogo: `productos.php`
- Detalle de producto: `producto.php`

### Carrito y compra

- Carrito: `carrito.php`
- Proceso de compra: `checkout.php`

### Pedidos y facturación

- Consulta privada de pedido: `ver_pedido.php`
- Factura PDF: `factura_pdf.php`
- Factura pública verificable por token: `factura_publica.php`

### Administración

- Panel administrativo: `admin/`

### Chatbot

- Endpoint del chatbot: `chatbot_api.php`

