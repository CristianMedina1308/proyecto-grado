<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function appSesionUsuario(array $usuario): array
{
    return [
        'id' => (int) ($usuario['id'] ?? 0),
        'nombre' => trim((string) ($usuario['nombre'] ?? '')),
        'email' => trim((string) ($usuario['email'] ?? '')),
        'rol' => trim((string) ($usuario['rol'] ?? 'cliente')) ?: 'cliente'
    ];
}

function appLoginUsuario(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = appSesionUsuario($usuario);
}

function appLogoutUsuario(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function appFlash(string $type, string $message, string $title = ''): void
{
    if (!isset($_SESSION['app_flashes']) || !is_array($_SESSION['app_flashes'])) {
        $_SESSION['app_flashes'] = [];
    }

    $_SESSION['app_flashes'][] = [
        'type' => trim($type) !== '' ? trim($type) : 'info',
        'title' => trim($title),
        'message' => trim($message)
    ];
}

function appPullFlashes(): array
{
    $flashes = $_SESSION['app_flashes'] ?? [];
    unset($_SESSION['app_flashes']);

    return is_array($flashes) ? array_values($flashes) : [];
}

function appCsrfToken(string $key = 'default'): string
{
    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    if (
        empty($_SESSION['csrf_tokens'][$key]) ||
        !is_string($_SESSION['csrf_tokens'][$key])
    ) {
        $_SESSION['csrf_tokens'][$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_tokens'][$key];
}

function appValidarCsrf(string $key, ?string $token): bool
{
    $stored = $_SESSION['csrf_tokens'][$key] ?? '';
    $userToken = is_string($token) ? $token : '';

    if ($stored === '' || $userToken === '') {
        return false;
    }

    $ok = hash_equals($stored, $userToken);

    if ($ok) {
        $_SESSION['csrf_tokens'][$key] = bin2hex(random_bytes(32));
    }

    return $ok;
}

function appValidarCsrfPersistente(string $key, ?string $token): bool
{
    $stored = $_SESSION['csrf_tokens'][$key] ?? '';
    $userToken = is_string($token) ? $token : '';

    if ($stored === '' || $userToken === '') {
        return false;
    }

    return hash_equals($stored, $userToken);
}

function appStoreProductImage(array $file, string $destinationDir): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No se selecciono ninguna imagen.');
    }

    if (
        $error !== UPLOAD_ERR_OK ||
        empty($file['tmp_name']) ||
        !is_uploaded_file((string) $file['tmp_name'])
    ) {
        throw new RuntimeException('No fue posible procesar la imagen subida.');
    }

    $tmpName = (string) $file['tmp_name'];
    $originalName = (string) ($file['name'] ?? 'imagen');
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $mimeType = '';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpName);
    }

    if (isset($allowedMime[$mimeType])) {
        $finalExtension = $allowedMime[$mimeType];
    } elseif (in_array($extension, $allowedExtensions, true)) {
        $finalExtension = $extension === 'jpeg' ? 'jpg' : $extension;
    } else {
        throw new RuntimeException('Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');
    }

    if (@getimagesize($tmpName) === false) {
        throw new RuntimeException('El archivo seleccionado no es una imagen valida.');
    }

    if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
        throw new RuntimeException('No se pudo preparar la carpeta de imagenes.');
    }

    $filename = 'prod-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $finalExtension;
    $targetPath = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('No fue posible guardar la imagen subida.');
    }

    return $filename;
}

function appDeleteProductImageFile(string $destinationDir, ?string $filename): void
{
    $safeName = basename(trim((string) $filename));

    if ($safeName === '' || $safeName === 'look-default.svg') {
        return;
    }

    $basePath = realpath($destinationDir);
    if ($basePath === false) {
        return;
    }

    $filePath = $basePath . DIRECTORY_SEPARATOR . $safeName;

    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

function appRedirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
    $_SESSION['usuario'] = appSesionUsuario($_SESSION['usuario']);
}
