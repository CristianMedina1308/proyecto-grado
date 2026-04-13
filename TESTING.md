# Testing Tauro Store

Este proyecto incluye una base de pruebas unitarias con `PHPUnit` para validar funciones core, validaciones de entrada y reglas de negocio reutilizables.

## Estructura

```text
tests/
├── bootstrap.php
└── Unit/
    ├── AppFunctionsTest.php
    ├── ValidationTest.php
    └── BusinessLogicTest.php
```

## Cobertura principal

- `includes/app.php`: sesión, flashes, CSRF y borrado seguro de imágenes.
- `includes/pedidos_utils.php`: estados permitidos, transiciones y normalización.
- `includes/chatbot_utils.php`: formateo y extracción básica de referencias.
- `includes/business_rules.php`: reglas puras para carrito, cantidades, impuestos y tallas.

## Ejecución

```powershell
composer install
vendor/bin/phpunit
run-tests.bat all
run-tests.bat security
run-tests.bat coverage
```

Si no usas Composer en esta máquina, también puedes ejecutar:

```powershell
php tools/phpunit.phar
```

## Trazabilidad resumida

- `REQ-001 Autenticación`: `AppFunctionsTest`
- `REQ-002 CSRF`: `AppFunctionsTest`
- `REQ-003 Validación de entrada`: `ValidationTest`
- `REQ-004 Carrito`: `BusinessLogicTest`
- `REQ-005 Estados de pedido`: `BusinessLogicTest`

## Notas

- Las pruebas están diseñadas para ser unitarias y no depender de base de datos real.
- La subida real de imágenes, envío de correo y generación completa de PDF siguen siendo mejores candidatas para pruebas de integración.
