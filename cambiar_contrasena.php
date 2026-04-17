<?php
require_once __DIR__ . '/includes/app.php';

// Ruta legada: se dejó para no romper enlaces viejos, pero ya no se usa.
appFlash('info', 'La recuperacion por codigo fue reemplazada por el PIN de 4 digitos. Usa el formulario de recuperar contraseña.', 'Recuperacion actualizada');
appRedirect('recuperar.php');

// No debe renderizar nada: appRedirect() termina la ejecución.
