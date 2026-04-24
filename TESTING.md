# Guía de pruebas - Tauro Store

Este documento concentra toda la información necesaria para usar el sistema de pruebas del proyecto. La idea es que cualquier persona pueda entrar aquí y entender qué se prueba, cómo ejecutar las pruebas, dónde revisar el resultado y qué hacer si algo falla.

Si necesitas validar cambios antes de entregar, revisar el estado del sistema o consultar el historial de ejecuciones, este es el único archivo que debes usar como referencia.

## Antes de empezar

Para ejecutar las pruebas conviene tener listo lo siguiente:

- PHP disponible desde consola.
- El proyecto abierto en la carpeta raíz `integrador-main`.
- PHPUnit disponible, ya sea desde `tools/phpunit.phar` o desde `vendor/bin/phpunit`.
- Si quieres guardar historial y consultar el panel, la base de datos debe estar accesible para el flujo con reportes.

En este proyecto, normalmente trabajarás desde PowerShell dentro de:

```powershell
C:\xampp\htdocs\integrador-main
```

## Qué cubren las pruebas

Las pruebas actuales validan la lógica más sensible del proyecto. Se revisan comportamientos que deberían mantenerse estables aunque cambie la interfaz o se hagan ajustes internos en el código.

Entre los puntos cubiertos están:

- Funciones generales de la aplicación, como manejo de sesión, mensajes temporales, CSRF y utilidades compartidas.
- Validaciones de entrada para evitar datos inválidos o incompletos.
- Reglas de negocio del carrito, cantidades, impuestos, tallas y flujo básico de pedidos.
- Utilidades internas que no deberían depender de una base de datos real para poder probarse.

El objetivo no es solo confirmar que el código corre, sino comprobar que la lógica importante del sistema sigue respondiendo como se espera después de cada cambio.

## Estructura relacionada

La parte principal de pruebas está organizada así:

```text
tests/
├── bootstrap.php
└── Unit/
    ├── AppFunctionsTest.php
    ├── ValidationTest.php
    └── BusinessLogicTest.php
```

Archivo de configuración:

- `phpunit.xml`

Archivos usados para generar y consultar reportes:

- `run-tests-reporter-db.php`
- `run-tests-reporter.php`
- `reports/api-db.php`
- `reports/index-db.html`

## Formas de ejecutar las pruebas

### Opción recomendada

Si quieres ejecutar las pruebas y además guardar el resultado para consultarlo después desde el panel o desde la API, usa este comando:

```powershell
php run-tests-reporter-db.php
```

Esta opción es la más útil porque deja trazabilidad de cada ejecución y funciona mejor cuando quieres conservar historial.

Pasos recomendados:

1. Abre PowerShell en la raíz del proyecto.
2. Ejecuta `php run-tests-reporter-db.php`.
3. Espera a que termine la corrida.
4. Revisa el estado final mostrado por consola.
5. Abre el panel o consulta la API si necesitas revisar detalles.

### Ejecución directa con PHPUnit

Si solo necesitas correr las pruebas sin generar el panel ni guardar el reporte en la base de datos, puedes ejecutar PHPUnit directamente.

Sin Composer:

```powershell
php tools/phpunit.phar
```

Con Composer:

```powershell
composer install
vendor/bin/phpunit
```

Esta opción sirve cuando solo quieres validar rápidamente si la suite pasa, sin guardar trazabilidad adicional.

## Dónde ver los resultados

Después de ejecutar `php run-tests-reporter-db.php`, puedes revisar la información desde estas rutas:

Panel visual:

- `http://localhost/integrador-main/reports/index-db.html`

API:

- `http://localhost/integrador-main/reports/api-db.php?action=latest`
- `http://localhost/integrador-main/reports/api-db.php?action=list`

El panel permite ver el resultado más reciente y el historial de ejecuciones. La API sirve si luego quieres integrar esa información con otra vista o consumirla desde otro proceso.

## Flujo recomendado de uso

Una forma práctica de trabajar con esta guía es la siguiente:

1. Haz tus cambios en el código.
2. Ejecuta `php run-tests-reporter-db.php`.
3. Si todo sale bien, revisa de forma rápida el panel para confirmar la ejecución.
4. Si algo falla, identifica la prueba afectada y corrige antes de seguir.
5. Vuelve a ejecutar hasta confirmar un resultado estable.

## Qué se guarda en cada ejecución

Cuando usas el generador de reportes, el sistema registra:

- Fecha y hora de la ejecución.
- Estado general de la corrida.
- Total de pruebas ejecutadas.
- Cantidad de pruebas exitosas, fallidas y omitidas.
- Porcentaje de éxito.
- Detalle de las pruebas que se ejecutaron.

La información queda persistida en la tabla `reportes_pruebas` y también puede existir respaldo dentro de `reports/`, según el flujo utilizado.

Esto permite llevar control sobre lo que pasó en cada ejecución sin depender solo de la salida momentánea de la consola.

## Cómo interpretar el resultado

Si el estado final aparece como exitoso, significa que la suite terminó sin fallos reales.

Si aparece como falló, conviene revisar primero:

- Qué prueba falló.
- Si el error viene de una validación, una regla de negocio o una dependencia del entorno.
- Si el fallo es nuevo o si ya venía ocurriendo desde ejecuciones anteriores.

Las advertencias de configuración no siempre significan que la lógica del proyecto esté rota. Lo importante es distinguir entre una advertencia del entorno y un fallo real de las pruebas.

Si quieres una lectura rápida del resultado:

- Exitoso: la suite terminó bien y no se detectaron fallos reales.
- Falló: al menos una prueba no cumplió el comportamiento esperado.
- Advertencias: revisa si son del entorno o de configuración antes de asumir que el código funcional está roto.

## Relación entre requerimientos y suites

Como referencia rápida:

- `REQ-001 Autenticación`: `AppFunctionsTest`
- `REQ-002 CSRF`: `AppFunctionsTest`
- `REQ-003 Validación de entrada`: `ValidationTest`
- `REQ-004 Carrito`: `BusinessLogicTest`
- `REQ-005 Estados de pedido`: `BusinessLogicTest`

## Problemas comunes

### No abre el panel de resultados

Verifica que la URL usada sea la correcta y que ya exista al menos una ejecución guardada. Si no hay resultados previos, el panel no tendrá información para mostrar.

### El comando no encuentra PHPUnit

Prueba primero con:

```powershell
php tools/phpunit.phar
```

Si trabajas con Composer y esa instalación ya está preparada, usa:

```powershell
vendor/bin/phpunit
```

### La fecha u hora no coincide con la zona esperada

Puedes definir la zona horaria antes de ejecutar:

```powershell
$env:APP_TIMEZONE="America/Bogota"
php run-tests-reporter-db.php
```

### PHPUnit muestra advertencias

Si las advertencias son de configuración, el sistema puede seguir generando el reporte. Aun así, si hay fallos reales en los tests, el resultado quedará marcado como fallido.

### No aparece información en el panel

Primero verifica que hayas ejecutado el generador de reportes. Si no hay una ejecución previa registrada, el panel no tendrá datos para mostrar.

## Recomendación de uso

Para trabajo diario, lo más práctico es usar siempre `php run-tests-reporter-db.php`. Ese flujo deja evidencia de cada ejecución y facilita revisar resultados sin volver a correr todo solo para consultar el estado.

Si solo necesitas una validación rápida y momentánea, puedes usar PHPUnit directo. Si necesitas control, seguimiento y consulta posterior, usa el flujo con reporte.
