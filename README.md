# Plataforma web de moda masculina

Aplicacion web para la venta de ropa y accesorios para hombres.
El sistema permite explorar catalogo, gestionar carrito, finalizar compra, descargar factura y administrar productos, pedidos y usuarios desde panel admin.

## Instalacion rapida

1. Clona este repositorio.
2. Crea una base de datos en MySQL (actual en este proyecto: `maquillaje`).
3. Importa tu script SQL de estructura/datos.
4. Ejecuta la migracion de mejoras reales del proyecto:
   - `migracion_realismo.sql`
5. Configura variables de entorno de base de datos si aplica:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
6. Ejecuta el servidor local:
   - `php -S localhost:8000`
7. Abre `http://localhost:8000` en tu navegador.

## Nota

El proyecto usa PHP, MySQL, Bootstrap y PHPMailer.
