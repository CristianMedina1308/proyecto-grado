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

/**
 * Resuelve el nombre de archivo de imagen a mostrar para un producto.
 *
 * - Usa el nombre almacenado en BD si existe en disco.
 * - Si no existe, intenta con la misma base pero otras extensiones comunes.
 * - Si aun no existe, asigna una imagen de tu carpeta (camisa/saco/mochila)
 *   segun el nombre/categoria del producto.
 *
 * Retorna solo el nombre del archivo (basename) dentro de $imagesDir.
 */
function appResolveProductImage(array $product, string $imagesDir): string
{
    $imagesDir = rtrim($imagesDir, DIRECTORY_SEPARATOR);
    $fallback = 'look-default.svg';

    $raw = (string) ($product['imagen'] ?? '');
    $filename = basename(trim($raw));

    if ($filename !== '' && is_file($imagesDir . DIRECTORY_SEPARATOR . $filename)) {
        return $filename;
    }

    if ($filename !== '') {
        $base = (string) pathinfo($filename, PATHINFO_FILENAME);
        $exts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];

        foreach ($exts as $ext) {
            $candidate = $base . '.' . $ext;
            if (is_file($imagesDir . DIRECTORY_SEPARATOR . $candidate)) {
                return $candidate;
            }
        }
    }

    $nombre = strtolower(trim((string) ($product['nombre'] ?? '')));
    $categoria = strtolower(trim((string) ($product['categoria'] ?? '')));

    $group = 'saco';
    if (str_contains($nombre, 'mochila') || str_contains($categoria, 'mochila')) {
        $group = 'mochila';
    } elseif (
        str_contains($categoria, 'camis') ||
        str_contains($nombre, 'camis') ||
        str_contains($nombre, 'polo')
    ) {
        $group = 'camisa';
    } elseif (
        str_contains($categoria, 'chaquet') ||
        str_contains($categoria, 'buzo') ||
        str_contains($nombre, 'chaqueta') ||
        str_contains($nombre, 'hoodie') ||
        str_contains($nombre, 'saco')
    ) {
        $group = 'saco';
    }

    static $cache = [];
    $cacheKey = $imagesDir . '|' . $group;

    if (!array_key_exists($cacheKey, $cache)) {
        $files = [];
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'] as $ext) {
            $pattern = $imagesDir . DIRECTORY_SEPARATOR . $group . '*.' . $ext;
            $matches = glob($pattern) ?: [];
            foreach ($matches as $match) {
                $files[] = $match;
            }
        }

        $files = array_values(array_unique($files));
        usort($files, static function (string $a, string $b): int {
            return strnatcasecmp(basename($a), basename($b));
        });

        $cache[$cacheKey] = $files;
    }

    $files = $cache[$cacheKey];
    if (is_array($files) && count($files) > 0) {
        $seed = (int) ($product['id'] ?? 0);
        if ($seed <= 0) {
            $seed = (int) (abs(crc32($nombre . '|' . $categoria)) ?: 1);
        }

        $index = $seed % count($files);
        return basename($files[$index]);
    }

    if (is_file($imagesDir . DIRECTORY_SEPARATOR . $fallback)) {
        return $fallback;
    }

    return $filename !== '' ? $filename : $fallback;
}

/**
 * Crea/actualiza productos a partir de imagenes locales (camisa*.png, saco*.png, mochila*.png).
 *
 * Objetivo: que el catalogo tenga un producto por imagen y que todos apunten a archivos existentes.
 * - Si un producto existente tiene una imagen que no existe, se le asigna una disponible.
 * - Luego, por cada imagen restante sin producto, se crea un producto nuevo con precio y datos base.
 *
 * Retorna un resumen con los contadores de actualizados/creados.
 */
function appListCatalogImageFiles(string $imagesDir): array
{
    $imagesDir = rtrim($imagesDir, DIRECTORY_SEPARATOR);
    if (!is_dir($imagesDir)) {
        return [];
    }

    $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $regex = '/^(camisa|saco|mochila)(\d+)\.(' . implode('|', $allowedExt) . ')$/i';
    $files = [];

    foreach ($allowedExt as $ext) {
        $matches = glob($imagesDir . DIRECTORY_SEPARATOR . '*.' . $ext) ?: [];
        foreach ($matches as $path) {
            $name = basename($path);
            if (preg_match($regex, $name)) {
                $files[] = $name;
            }
        }
    }

    $files = array_values(array_unique($files));
    usort($files, 'strnatcasecmp');

    return $files;
}

function appSeedCatalogFromImages(PDO $conn, string $imagesDir): array
{
    $imagesDir = rtrim($imagesDir, DIRECTORY_SEPARATOR);
    if (!is_dir($imagesDir)) {
        return ['updated' => 0, 'inserted' => 0, 'totalImages' => 0];
    }

    $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $regex = '/^(camisa|saco|mochila)(\d+)\.(' . implode('|', $allowedExt) . ')$/i';

    $files = appListCatalogImageFiles($imagesDir);

    if (!$files) {
        return ['updated' => 0, 'inserted' => 0, 'totalImages' => 0];
    }

    $byGroup = ['camisa' => [], 'saco' => [], 'mochila' => []];
    foreach ($files as $file) {
        if (preg_match($regex, $file, $m)) {
            $group = strtolower($m[1]);
            $byGroup[$group][] = $file;
        }
    }

    $productos = $conn->query('SELECT id, nombre, categoria, imagen FROM productos ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

    // Marca como usadas las imagenes ya asignadas y existentes.
    $used = [];
    foreach ($productos as $p) {
        $img = basename(trim((string) ($p['imagen'] ?? '')));
        if ($img !== '' && in_array($img, $files, true) && is_file($imagesDir . DIRECTORY_SEPARATOR . $img)) {
            $used[$img] = true;
        }
    }

    $pool = ['camisa' => [], 'saco' => [], 'mochila' => []];
    foreach ($byGroup as $group => $list) {
        foreach ($list as $img) {
            if (!isset($used[$img])) {
                $pool[$group][] = $img;
            }
        }
    }

    $pickFromAnyPool = static function (array &$pool): ?string {
        foreach (['camisa', 'saco', 'mochila'] as $g) {
            if (!empty($pool[$g])) {
                return array_shift($pool[$g]);
            }
        }
        return null;
    };

    $updated = 0;
    $inserted = 0;

    $updStmt = $conn->prepare('UPDATE productos SET imagen = ? WHERE id = ?');
    $galCountStmt = $conn->prepare('SELECT COUNT(*) FROM producto_imagenes WHERE producto_id = ?');
    $galInsStmt = $conn->prepare('INSERT INTO producto_imagenes (producto_id, archivo) VALUES (?, ?)');

    // 1) Actualiza productos existentes con imagen rota.
    foreach ($productos as $p) {
        $img = basename(trim((string) ($p['imagen'] ?? '')));
        if ($img !== '' && is_file($imagesDir . DIRECTORY_SEPARATOR . $img)) {
            continue;
        }

        $group = 'saco';
        $nombre = strtolower(trim((string) ($p['nombre'] ?? '')));
        $categoria = strtolower(trim((string) ($p['categoria'] ?? '')));
        if (str_contains($nombre, 'mochila') || str_contains($categoria, 'mochila')) {
            $group = 'mochila';
        } elseif (str_contains($categoria, 'camis') || str_contains($nombre, 'camis') || str_contains($nombre, 'polo')) {
            $group = 'camisa';
        } elseif (str_contains($categoria, 'chaquet') || str_contains($categoria, 'buzo') || str_contains($nombre, 'chaqueta') || str_contains($nombre, 'hoodie') || str_contains($nombre, 'saco')) {
            $group = 'saco';
        }

        $picked = null;
        if (!empty($pool[$group])) {
            $picked = array_shift($pool[$group]);
        } else {
            $picked = $pickFromAnyPool($pool);
        }

        if ($picked === null) {
            break;
        }

        $updStmt->execute([$picked, (int) $p['id']]);
        $updated += $updStmt->rowCount() > 0 ? 1 : 0;

        // Asegura al menos 1 imagen en galeria.
        $galCountStmt->execute([(int) $p['id']]);
        if ((int) $galCountStmt->fetchColumn() === 0) {
            $galInsStmt->execute([(int) $p['id'], $picked]);
        }
    }

    // 2) Inserta productos nuevos para las imagenes restantes.
    $insStmt = $conn->prepare('INSERT INTO productos (nombre, sku, descripcion, precio, categoria, marca, color, material, fit, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $skuCheckStmt = $conn->prepare('SELECT COUNT(*) FROM productos WHERE sku = ?');
    $defaultPrice = 100000.00;
    $defaultMarca = 'Tauro';
    $defaultFit = 'Regular';

    foreach (['camisa', 'saco', 'mochila'] as $group) {
        while (!empty($pool[$group])) {
            $img = array_shift($pool[$group]);
            if (!$img) {
                continue;
            }

            preg_match($regex, $img, $m);
            $num = isset($m[2]) ? (int) $m[2] : 0;
            $nombre = ucfirst($group) . ($num > 0 ? ' ' . $num : '');
            $categoria = $group === 'camisa' ? 'Camisas' : ($group === 'mochila' ? 'Mochilas' : 'Sacos');

            // SKU estable por imagen (evita depender del autoincrement y ayuda a filtrar por catalogo).
            $skuBase = 'TS-' . strtoupper($group) . '-' . str_pad((string) max(1, $num), 2, '0', STR_PAD_LEFT);
            $sku = $skuBase;
            $skuCheckStmt->execute([$sku]);
            if ((int) $skuCheckStmt->fetchColumn() > 0) {
                $sku = $skuBase . '-' . substr(md5($img), 0, 4);
            }

            $insStmt->execute([
                $nombre,
                $sku,
                null,
                $defaultPrice,
                $categoria,
                $defaultMarca,
                null,
                null,
                $defaultFit,
                $img
            ]);

            $newId = (int) $conn->lastInsertId();
            if ($newId > 0) {
                $galInsStmt->execute([$newId, $img]);
                $inserted++;
            }
        }
    }

    return ['updated' => $updated, 'inserted' => $inserted, 'totalImages' => count($files)];
}

function appRedirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
    $_SESSION['usuario'] = appSesionUsuario($_SESSION['usuario']);
}
