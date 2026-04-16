# Pruebas unitarias - Tauro Store

Este proyecto incluye una base de pruebas unitarias con `PHPUnit` para validar funciones core, validaciones de entrada y reglas de negocio reutilizables.

---

## Estructura

```text
tests/
├── bootstrap.php
└── Unit/
    ├── AppFunctionsTest.php
    ├── ValidationTest.php
    └── BusinessLogicTest.php
```

---

## Entrada principal

- `phpunit.xml`

---

## Cobertura principal

- `includes/app.php`: sesión, flashes, CSRF y borrado seguro de imágenes.
- `includes/pedidos_utils.php`: estados permitidos, transiciones y normalización.
- `includes/chatbot_utils.php`: formateo y extracción básica de referencias.
- `includes/business_rules.php`: reglas puras para carrito, cantidades, impuestos y tallas.

---

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

---

## Trazabilidad resumida

- `REQ-001 Autenticación`: `AppFunctionsTest`
- `REQ-002 CSRF`: `AppFunctionsTest`
- `REQ-003 Validación de entrada`: `ValidationTest`
- `REQ-004 Carrito`: `BusinessLogicTest`
- `REQ-005 Estados de pedido`: `BusinessLogicTest`

---

## Resultados de pruebas unitarias

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

## Notas

- Las pruebas están diseñadas para ser unitarias y no depender de base de datos real.
