<?php
require_once __DIR__ . '/includes/app.php';

// Este flujo estaba basado en "codigo por WhatsApp" (simulado). Se deja redirección para evitar bypass.
appFlash('info', 'La recuperacion por codigo fue reemplazada por el PIN de 4 digitos. Usa el formulario de recuperar contraseña.', 'Recuperacion actualizada');
appRedirect('recuperar.php');
// No debe renderizar nada: appRedirect() termina la ejecución.
