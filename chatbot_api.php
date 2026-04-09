<?php

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/chatbot_utils.php';
require_once __DIR__ . '/includes/conexion.php';

header('Content-Type: application/json; charset=utf-8');

function chatbotApiRespond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    chatbotApiRespond(405, [
        'ok' => false,
        'message' => 'Este asistente solo acepta solicitudes POST.',
        'suggestions' => ['Arma un outfit', 'Guia de tallas', 'Envios', 'Facturas']
    ]);
}

$rawBody = file_get_contents('php://input');
$payload = json_decode(is_string($rawBody) ? $rawBody : '', true);

if (!is_array($payload)) {
    chatbotApiRespond(422, [
        'ok' => false,
        'message' => 'No pude leer el mensaje del chat. Intenta de nuevo.',
        'suggestions' => ['Arma un outfit', 'Guia de tallas', 'Envios', 'Facturas']
    ]);
}

$message = trim((string) ($payload['message'] ?? ''));
$csrfToken = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : null;
$shouldResetConversation = !empty($payload['reset']);

if (!appValidarCsrfPersistente('chatbot_publico', $csrfToken)) {
    tauroChatbotResetConversation();

    chatbotApiRespond(419, [
        'ok' => false,
        'message' => 'La sesion del chat expiro. Recarga la pagina para continuar.',
        'suggestions' => ['Arma un outfit', 'Guia de tallas', 'Envios', 'Facturas'],
        'reset' => true
    ]);
}

if ($shouldResetConversation) {
    tauroChatbotResetConversation();
}

if ($message === '') {
    chatbotApiRespond(422, [
        'ok' => false,
        'message' => 'Escribe una pregunta para que pueda ayudarte.',
        'suggestions' => ['Arma un outfit', 'Guia de tallas', 'Envios', 'Facturas']
    ]);
}

if (tauroChatbotStringLength($message) > 500) {
    chatbotApiRespond(422, [
        'ok' => false,
        'message' => 'Tu mensaje es un poco largo. Intenta resumirlo en una sola pregunta o en menos de 500 caracteres.',
        'suggestions' => tauroChatbotSuggestPrompts($message)
    ]);
}

$rateLimit = tauroChatbotCheckRateLimit();

if (!$rateLimit['allowed']) {
    chatbotApiRespond(429, [
        'ok' => false,
        'message' => 'Hagamos una pausa breve. Intenta nuevamente en ' . (int) $rateLimit['retry_after'] . ' segundos.',
        'suggestions' => tauroChatbotSuggestPrompts($message)
    ]);
}

$reply = tauroChatbotBuildReply($conn, $message);

chatbotApiRespond(200, [
    'ok' => true,
    'message' => (string) ($reply['text'] ?? ''),
    'suggestions' => (array) ($reply['suggestions'] ?? tauroChatbotSuggestPrompts($message)),
    'mode' => (string) ($reply['mode'] ?? 'fallback')
]);
