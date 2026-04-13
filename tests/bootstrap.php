<?php

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
} else {
    session_start();
}

$_SESSION = [];

require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/pedidos_utils.php';
require_once __DIR__ . '/../includes/chatbot_utils.php';
require_once __DIR__ . '/../includes/business_rules.php';
