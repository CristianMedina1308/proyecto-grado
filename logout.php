<?php
require_once 'includes/app.php';

appLogoutUsuario();
session_start();
appFlash('success', 'Tu sesion se cerro correctamente.', 'Hasta pronto');
appRedirect('index.php');
