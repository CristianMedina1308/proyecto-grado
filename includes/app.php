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
 * Devuelve el archivo de imagen que se debe mostrar para un producto.
 *
 * Orden de resolucion:
 * 1) Se respeta el nombre guardado en BD si el archivo existe.
 * 2) Si no existe, se prueba la misma base con otras extensiones comunes.
 * 3) Si aun no existe, se asigna una imagen disponible (camisa/saco/mochila)
 *    en base a nombre/categoria.
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
 * Lista las imagenes del catalogo (camisa/saco/mochila) disponibles en disco.
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

/**
 * Genera un nombre y una descripcion coherentes a partir del archivo de imagen.
 *
 * Nota: esto se usa para completar el copy del catalogo (nombre/descripcion)
 * sin tocar precios ni otras columnas.
 */
function appCatalogCopyFromImageFilename(string $filename, array $product = []): array
{
    $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $regex = '/^(camisa|saco|mochila)(\d+)\.(' . implode('|', $allowedExt) . ')$/i';

    $base = basename(trim($filename));
    $group = 'saco';
    $num = 1;
    if (preg_match($regex, $base, $m)) {
        $group = strtolower($m[1]);
        $num = max(1, (int) $m[2]);
    }

    $colors = [
        'Negro', 'Blanco', 'Azul', 'Gris', 'Beige', 'Verde oliva', 'Vinotinto', 'Cafe', 'Arena',
        'Azul marino', 'Mostaza', 'Grafito', 'Camel', 'Hueso', 'Acero', 'Carbon', 'Chocolate',
        'Tostado', 'Pizarra', 'Taupe'
    ];

    $fit = trim((string) ($product['fit'] ?? ''));
    if ($fit === '') {
        $fit = 'Regular';
    }

    $material = trim((string) ($product['material'] ?? ''));
    if ($material === '') {
        $material = $group === 'camisa' ? 'Algodon' : ($group === 'mochila' ? 'Poliester' : 'Mezcla textil');
    }

    $color = trim((string) ($product['color'] ?? ''));
    if ($color === '') {
        $color = $colors[($num - 1) % count($colors)];
    }

    $marca = trim((string) ($product['marca'] ?? ''));
    if ($marca === '') {
        $marca = 'Tauro';
    }

    if ($group === 'camisa') {
        $styles = [
            'Oxford', 'Slim Fit', 'Lino', 'Manga Larga', 'Formal', 'Casual', 'Premium', 'Minimal',
            'Texturizada', 'Denim', 'Essential', 'Urban', 'Cuello Mao', 'Clasica', 'Sastrera'
        ];
        $style = $styles[($num - 1) % count($styles)];
        $name = 'Camisa ' . $style . ' ' . $color;
        $desc = 'Camisa ' . strtolower($style) . ' para hombre en tono ' . strtolower($color) . ', fit ' . strtolower($fit) . '. '
            . 'Tela ' . strtolower($material) . ' comoda para oficina o salidas; se ve excelente con jean o pantalon de vestir.';
        return ['nombre' => $name, 'descripcion' => $desc];
    }

    if ($group === 'mochila') {
        $styles = [
            'Urbana', 'Compacta', 'Ejecutiva', 'Travel', 'Minimal', 'Waterproof', 'Daily', 'Campus', 'Street'
        ];
        $style = $styles[($num - 1) % count($styles)];
        $name = 'Mochila ' . $style . ' ' . $color;
        $desc = 'Mochila ' . strtolower($style) . ' para hombre en color ' . strtolower($color) . '. '
            . 'Compartimentos organizados y material ' . strtolower($material) . ' para uso diario en trabajo, estudio o viaje.';
        return ['nombre' => $name, 'descripcion' => $desc];
    }

    // saco
    $styles = [
        'Bomber', 'Denim', 'Biker', 'Tejido', 'Hoodie', 'Varsity', 'Puffer', 'Pano', 'Cargo', 'Aviador',
        'Rompevientos', 'Cuello Alto', 'Overshirt', 'Basico', 'Street', 'Tactical', 'Minimal', 'Utility',
        'Premium', 'Classic'
    ];
    $style = $styles[($num - 1) % count($styles)];
    $name = 'Chaqueta ' . $style . ' ' . $color;
    $desc = 'Chaqueta estilo ' . strtolower($style) . ' en tono ' . strtolower($color) . ', pensada para capas y clima variable. '
        . 'Acabado ' . strtolower($marca) . ' y material ' . strtolower($material) . ' para un look masculino sobrio y moderno.';

    return ['nombre' => $name, 'descripcion' => $desc];
}

function appGenericProductDescription(array $product): string
{
    $nombre = trim((string) ($product['nombre'] ?? ''));
    $categoria = strtolower(trim((string) ($product['categoria'] ?? '')));
    $marca = trim((string) ($product['marca'] ?? ''));
    $color = trim((string) ($product['color'] ?? ''));
    $material = trim((string) ($product['material'] ?? ''));
    $fit = trim((string) ($product['fit'] ?? ''));

    if ($marca === '') {
        $marca = 'Tauro';
    }
    if ($fit === '') {
        $fit = 'Regular';
    }

    $tipo = 'Producto';
    if (str_contains($categoria, 'camis')) {
        $tipo = 'Camisa';
    } elseif (str_contains($categoria, 'saco') || str_contains($categoria, 'chaquet') || str_contains($categoria, 'buzo')) {
        $tipo = 'Chaqueta';
    } elseif (str_contains($categoria, 'mochil')) {
        $tipo = 'Mochila';
    } elseif (str_contains($categoria, 'jean') || str_contains($categoria, 'pantal')) {
        $tipo = 'Pantalon';
    } elseif (str_contains($categoria, 'tenis') || str_contains($categoria, 'zap')) {
        $tipo = 'Calzado';
    }

    $detalles = [];
    if ($color !== '') {
        $detalles[] = 'color ' . strtolower($color);
    }
    if ($material !== '') {
        $detalles[] = 'material ' . strtolower($material);
    }
    if ($fit !== '') {
        $detalles[] = 'fit ' . strtolower($fit);
    }

    $detalleTxt = $detalles ? (' (' . implode(', ', $detalles) . ')') : '';
    $baseName = $nombre !== '' ? $nombre : ($tipo . ' ' . $marca);

    return $baseName . '. ' . $tipo . ' para hombre ' . $marca . $detalleTxt . ', ideal para un look sobrio y versatil en el dia a dia.';
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

    $productos = $conn->query('SELECT id, nombre, descripcion, categoria, marca, color, material, fit, imagen FROM productos ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

    // Marcar como usadas las imagenes ya asignadas y existentes.
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

    // Completar nombre/descripcion (solo si estan vacios o genericos).
    $copyUpdStmt = $conn->prepare('UPDATE productos SET nombre = ?, descripcion = ? WHERE id = ?');

    // 1) Reparar productos existentes con imagen rota.
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

    // 1.5) Completar nombre/descripcion para productos del catalogo.
    foreach ($productos as $p) {
        $img = basename(trim((string) ($p['imagen'] ?? '')));
        if ($img === '') {
            continue;
        }

        // Solo actuar sobre archivos del catalogo (camisa/saco/mochila).
        if (!preg_match($regex, $img)) {
            continue;
        }

        $nombreActual = trim((string) ($p['nombre'] ?? ''));
        $descActual = trim((string) ($p['descripcion'] ?? ''));
        $nombreEsGenerico = $nombreActual === '' || preg_match('/^(camisa|saco|mochila)\s*\d*$/i', $nombreActual);

        $descEsGenerica = $descActual !== '' && str_contains(strtolower($descActual), 'ideal para un look sobrio y versatil');

        if (!$nombreEsGenerico && $descActual !== '' && !$descEsGenerica) {
            continue;
        }

        $copy = appCatalogCopyFromImageFilename($img, $p);
        $nuevoNombre = $nombreEsGenerico ? (string) ($copy['nombre'] ?? $nombreActual) : $nombreActual;
        $nuevaDesc = ($descActual !== '' && !$descEsGenerica)
            ? $descActual
            : (string) ($copy['descripcion'] ?? $descActual);

        if ($nuevoNombre !== $nombreActual || $nuevaDesc !== $descActual) {
            $copyUpdStmt->execute([$nuevoNombre, $nuevaDesc !== '' ? $nuevaDesc : null, (int) $p['id']]);
        }
    }

    // 1.6) Completar descripciones faltantes en cualquier producto (sin tocar nombres no genericos).
    foreach ($productos as $p) {
        $descActual = trim((string) ($p['descripcion'] ?? ''));
        if ($descActual !== '') {
            continue;
        }

        $genericDesc = appGenericProductDescription($p);
        if (trim($genericDesc) === '') {
            continue;
        }

        $copyUpdStmt->execute([
            trim((string) ($p['nombre'] ?? '')),
            $genericDesc,
            (int) $p['id']
        ]);
    }

    // 2) Crear productos para las imagenes restantes.
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
            $copy = appCatalogCopyFromImageFilename($img, [
                'marca' => $defaultMarca,
                'fit' => $defaultFit
            ]);
            $nombre = (string) ($copy['nombre'] ?? (ucfirst($group) . ($num > 0 ? ' ' . $num : '')));
            $categoria = $group === 'camisa' ? 'Camisas' : ($group === 'mochila' ? 'Mochilas' : 'Sacos');

            // SKU estable por imagen (ayuda a mantener el catalogo identificable).
            $skuBase = 'TS-' . strtoupper($group) . '-' . str_pad((string) max(1, $num), 2, '0', STR_PAD_LEFT);
            $sku = $skuBase;
            $skuCheckStmt->execute([$sku]);
            if ((int) $skuCheckStmt->fetchColumn() > 0) {
                $sku = $skuBase . '-' . substr(md5($img), 0, 4);
            }

            $insStmt->execute([
                $nombre,
                $sku,
                (string) ($copy['descripcion'] ?? ''),
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

function appEnv(string $key, string $default = ''): string
{
    $value = getenv($key);
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    if (isset($_ENV[$key]) && is_string($_ENV[$key]) && trim($_ENV[$key]) !== '') {
        return trim($_ENV[$key]);
    }

    if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && trim($_SERVER[$key]) !== '') {
        return trim($_SERVER[$key]);
    }

    return $default;
}

/**
 * Comprueba si una columna existe en una tabla.
 *
 * Esto permite que el proyecto siga funcionando aunque la migración no se haya aplicado aún.
 */
function appDbHasColumn(PDO $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = strtolower($table . '.' . $column);

    if (array_key_exists($key, $cache)) {
        return (bool) $cache[$key];
    }

    try {
        $safeTable = str_replace('`', '', $table);
        $stmt = $conn->prepare('SHOW COLUMNS FROM `' . $safeTable . '` LIKE ?');
        $stmt->execute([$column]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

        // En algunos hosts/usuarios, SHOW COLUMNS puede estar restringido.
        // Si no encontramos nada, intentamos con INFORMATION_SCHEMA.
        if (!$exists) {
            $db = appDbCurrentDatabase($conn);
            if ($db !== '') {
                $chk = $conn->prepare(
                    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
                );
                $chk->execute([$db, $safeTable, $column]);
                $exists = $chk->fetchColumn() !== false;
            }
        }

        $cache[$key] = $exists;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return (bool) $cache[$key];
}

function appDbCurrentDatabase(PDO $conn): string
{
    try {
        $db = $conn->query('SELECT DATABASE()')->fetchColumn();
        return is_string($db) ? $db : '';
    } catch (Throwable $e) {
        return '';
    }
}

function appValidateRecoveryPin(string $pin): bool
{
    return preg_match('/^\d{4}$/', $pin) === 1;
}

function appIsWeakRecoveryPin(string $pin): bool
{
    // Evita los PIN más obvios. No es infalible, pero reduce errores comunes.
    static $weak = [
        '0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999',
        '1234', '4321', '1122', '1212', '2468', '1357'
    ];

    return in_array($pin, $weak, true);
}

function appHashRecoveryPin(string $pin): string
{
    $pepper = appEnv('APP_PIN_PEPPER', '');
    return password_hash($pepper . $pin, PASSWORD_DEFAULT);
}

function appVerifyRecoveryPin(string $pin, string $hash): bool
{
    if (trim($hash) === '') {
        return false;
    }

    $pepper = appEnv('APP_PIN_PEPPER', '');
    return password_verify($pepper . $pin, $hash);
}

function appPublicBaseUrl(): string
{
    $explicit = appEnv('APP_URL', '') ?: appEnv('PUBLIC_URL', '');
    if ($explicit !== '') {
        return rtrim($explicit, '/');
    }

    $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (strpos($forwardedProto, ',') !== false) {
        $forwardedProto = trim((string) explode(',', $forwardedProto)[0]);
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    if ($forwardedProto !== '') {
        $scheme = strtolower($forwardedProto) === 'https' ? 'https' : 'http';
    } elseif (appEnv('RAILWAY_PUBLIC_DOMAIN', '') !== '') {
        $scheme = 'https';
    }

    $host = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? appEnv('RAILWAY_PUBLIC_DOMAIN', 'localhost')));
    if (strpos($host, ',') !== false) {
        $host = trim((string) explode(',', $host)[0]);
    }

    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    $dir = rtrim($dir, '/');

    return $scheme . '://' . $host . ($dir !== '' ? $dir : '');
}

function appAbsoluteUrl(string $path): string
{
    return rtrim(appPublicBaseUrl(), '/') . '/' . ltrim($path, '/');
}

function appSendEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $host = appEnv('SMTP_HOST', '');
    $user = appEnv('SMTP_USER', '');
    $pass = appEnv('SMTP_PASS', '');
    if ($host === '' || $user === '' || $pass === '') {
        // Fallback: intentar con mail() si el servidor lo soporta.
        // Nota: en Windows/XAMPP depende de la configuración de php.ini (SMTP / sendmail_from).
        $fromEmail = appEnv('SMTP_FROM_EMAIL', '');
        if ($fromEmail === '') {
            $serverHost = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $serverHost = preg_replace('/:\d+$/', '', $serverHost);
            $fromEmail = 'no-reply@' . ($serverHost !== '' ? $serverHost : 'localhost');
        }

        $fromName = appEnv('SMTP_FROM_NAME', 'Tauro Store - Moda Masculina');
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';

        $to = $toName !== '' ? ($toName . ' <' . $toEmail . '>') : $toEmail;
        try {
            return function_exists('mail') ? mail($to, $subject, $htmlBody, implode("\r\n", $headers)) : false;
        } catch (Throwable $e) {
            return false;
        }
    }

    $port = (int) (appEnv('SMTP_PORT', '') !== '' ? appEnv('SMTP_PORT') : 587);
    $secure = strtolower(appEnv('SMTP_SECURE', $port === 465 ? 'ssl' : 'tls'));
    $fromEmail = appEnv('SMTP_FROM_EMAIL', $user);
    $fromName = appEnv('SMTP_FROM_NAME', 'Tauro Store - Moda Masculina');

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $base = __DIR__ . '/PHPMailer/';
        require_once $base . 'Exception.php';
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->Port = $port;

        if ($secure !== '') {
            $mail->SMTPSecure = $secure;
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
    $_SESSION['usuario'] = appSesionUsuario($_SESSION['usuario']);
}
