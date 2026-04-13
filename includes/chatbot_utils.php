<?php

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/pedidos_utils.php';

function tauroChatbotStringLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function tauroChatbotEnv(string $key, string $default = ''): string
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

function tauroChatbotNormalizeText(string $text): string
{
    if (function_exists('mb_strtolower')) {
        $normalized = trim(mb_strtolower($text, 'UTF-8'));
    } else {
        $normalized = trim(strtolower($text));
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($converted) && $converted !== '') {
            $normalized = strtolower($converted);
        }
    }

    $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

    return trim($normalized);
}

function tauroChatbotBaseUrl(): string
{
    $explicitBaseUrl = trim((string) (tauroChatbotEnv('APP_URL') ?: tauroChatbotEnv('PUBLIC_URL')));
    if ($explicitBaseUrl !== '') {
        return rtrim($explicitBaseUrl, '/');
    }

    $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (strpos($forwardedProto, ',') !== false) {
        $forwardedProto = trim((string) explode(',', $forwardedProto)[0]);
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';

    if ($forwardedProto !== '') {
        $scheme = strtolower($forwardedProto) === 'https' ? 'https' : 'http';
    } elseif (tauroChatbotEnv('RAILWAY_PUBLIC_DOMAIN') !== '') {
        $scheme = 'https';
    }

    $host = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? tauroChatbotEnv('RAILWAY_PUBLIC_DOMAIN', 'localhost')));
    if (strpos($host, ',') !== false) {
        $host = trim((string) explode(',', $host)[0]);
    }

    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim($dir, '/');

    return $scheme . '://' . $host . ($dir !== '' ? $dir : '');
}

function tauroChatbotAbsoluteUrl(string $path): string
{
    return rtrim(tauroChatbotBaseUrl(), '/') . '/' . ltrim($path, '/');
}

function tauroChatbotFormatPrice(float $price): string
{
    return '$' . number_format($price, 0, ',', '.');
}

function tauroChatbotFormatDate(?string $value): string
{
    $text = trim((string) $value);

    if ($text === '') {
        return 'Sin registro';
    }

    $timestamp = strtotime($text);

    if ($timestamp === false) {
        return $text;
    }

    return date('d/m/Y H:i', $timestamp);
}

function tauroChatbotDefaultSuggestions(): array
{
    return [
        'Arma un outfit',
        'Ver chaquetas negras',
        'Guia de tallas',
        'Consultar pedido con token'
    ];
}

function tauroChatbotKnowledgeBase(): array
{
    return [
        [
            'keywords' => ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'hey'],
            'answer' => [
                'text' => 'Hola. Soy el asistente de Tauro Store. Puedo ayudarte con productos, tallas, envios, compras, estilo masculino y seguimiento publico con token.',
                'suggestions' => tauroChatbotDefaultSuggestions()
            ]
        ],
        [
            'keywords' => ['como compro', 'comprar', 'hacer compra', 'checkout', 'quiero comprar'],
            'answer' => [
                'text' => 'Puedes elegir tu prenda, seleccionar talla, agregarla al carrito y finalizar en checkout. Si necesitas ayuda para decidir talla, combinacion o metodo de pago, tambien te acompano por aqui.',
                'suggestions' => ['Guia de tallas', 'Metodos de pago', 'Envios', 'Ver chaquetas negras']
            ]
        ],
        [
            'keywords' => ['pago', 'pagos', 'contra entrega', 'recoger en tienda', 'recogida', 'metodos de pago'],
            'answer' => [
                'text' => 'Ahora mismo la tienda maneja dos modalidades: contra entrega con domicilio y recoger en tienda. Si eliges contra entrega, debes completar los datos de envio durante el checkout.',
                'suggestions' => ['Contra entrega', 'Recoger en tienda', 'Envios', 'Consultar pedido con token']
            ]
        ],
        [
            'keywords' => ['envio', 'envios', 'domicilio', 'entrega', 'cuanto tarda', 'tiempo de entrega', 'costo de envio'],
            'answer' => [
                'text' => 'El valor del envio se calcula durante el checkout segun ciudad y zona. Para consultas generales te ayudo por aqui, y para un caso puntual siempre puedes confirmar el valor exacto antes de pagar.',
                'suggestions' => ['Bogota', 'Otras ciudades', 'Seguimiento', 'Metodos de pago']
            ]
        ],
        [
            'keywords' => ['talla', 'tallas', 'medidas', 'size', 'fit', 'stock', 'inventario'],
            'answer' => [
                'text' => 'Cada producto muestra tallas y disponibilidad. Si me dices tu contextura, altura o el tipo de ajuste que buscas, puedo orientarte mejor entre fit regular, slim o una compra mas comoda.',
                'suggestions' => ['Guia de tallas', 'Fit regular o slim', 'Arma un outfit', 'WhatsApp']
            ]
        ],
        [
            'keywords' => ['outfit', 'combinar', 'look', 'ropa para salir', 'ropa para cita', 'como vestir', 'recomiendame'],
            'answer' => [
                'text' => 'Puedo ayudarte a armar looks casuales, sobrios o mas elegantes. Si me dices la ocasion, el color base o la prenda principal, te propongo combinaciones con aire masculino y limpio.',
                'suggestions' => ['Outfit casual elegante', 'Look para oficina', 'Que zapatos combinar', 'Colores que combinan']
            ]
        ],
        [
            'keywords' => ['lavar', 'cuidar', 'cuidados', 'mantenimiento', 'prenda', 'material', 'encoger', 'manchas'],
            'answer' => [
                'text' => 'Tambien puedo orientarte sobre cuidado de prendas, lavado, secado y combinacion de materiales para que la ropa conserve mejor su forma y apariencia.',
                'suggestions' => ['Cuidado de prendas', 'Algodon premium', 'Como combinar colores', 'Arma un outfit']
            ]
        ],
        [
            'keywords' => ['pedido', 'seguimiento', 'tracking', 'donde va mi pedido', 'estado de pedido'],
            'answer' => [
                'text' => 'Desde este chat publico no puedo ver pedidos privados sin una referencia publica valida. Si tienes el token de tu factura, si puedo ayudarte a revisar el estado general y compartir el enlace de consulta.',
                'suggestions' => ['Consultar pedido con token', 'Facturas', 'Cambios o cancelaciones', 'WhatsApp']
            ]
        ],
        [
            'keywords' => ['factura', 'facturas', 'comprobante', 'pdf', 'qr'],
            'answer' => [
                'text' => 'Despues de una compra, la tienda puede generar una factura verificable. Si ya tienes un enlace o token publico, puedes usarlo aqui para consultar el estado general y abrir la factura.',
                'suggestions' => ['Consultar pedido con token', 'Seguimiento', 'Metodos de pago', 'WhatsApp']
            ]
        ],
        [
            'keywords' => ['cancelar', 'cancelacion', 'devolucion', 'cambio', 'reembolso'],
            'answer' => [
                'text' => 'Si el pedido aun no avanzo demasiado en el proceso, puede existir opcion de cancelacion o cambio. Para casos personales te conviene escribir por WhatsApp para revisar tiempos y estado real.',
                'suggestions' => ['WhatsApp', 'Seguimiento', 'Facturas', 'Contacto']
            ]
        ],
        [
            'keywords' => ['login', 'iniciar sesion', 'registrarme', 'crear cuenta', 'cuenta', 'usuario'],
            'answer' => [
                'text' => 'Puedes navegar la tienda y hablar conmigo sin crear cuenta. La cuenta te sirve sobre todo para ver historial, perfil y seguimiento privado.',
                'suggestions' => ['Como compro', 'Seguimiento', 'Recuperar contrasena', 'WhatsApp']
            ]
        ],
        [
            'keywords' => ['contacto', 'asesor', 'ayuda humana', 'telefono', 'correo', 'whatsapp'],
            'answer' => [
                'text' => 'Si prefieres apoyo humano, puedes escribir a WhatsApp al +57 302 334 1713. Mientras tanto, tambien puedo ayudarte con dudas de producto, estilo o proceso de compra.',
                'suggestions' => ['WhatsApp', 'Envios', 'Metodos de pago', 'Guia de tallas']
            ]
        ]
    ];
}

function tauroChatbotScoreIntent(string $message, array $intent): int
{
    $normalizedMessage = tauroChatbotNormalizeText($message);

    if ($normalizedMessage === '') {
        return 0;
    }

    $score = 0;

    foreach (($intent['keywords'] ?? []) as $keyword) {
        $normalizedKeyword = tauroChatbotNormalizeText((string) $keyword);

        if ($normalizedKeyword === '') {
            continue;
        }

        if ($normalizedMessage === $normalizedKeyword) {
            $score += 6;
            continue;
        }

        if (strpos($normalizedMessage, $normalizedKeyword) !== false) {
            $score += strpos($normalizedKeyword, ' ') !== false ? 4 : 2;
        }
    }

    return $score;
}

function tauroChatbotSuggestPrompts(string $message = ''): array
{
    $normalized = tauroChatbotNormalizeText($message);
    $map = [
        'envios' => ['envio', 'envios', 'domicilio', 'entrega', 'bogota', 'ciudad'],
        'tallas' => ['talla', 'tallas', 'fit', 'medidas', 'size'],
        'estilo' => ['outfit', 'combinar', 'look', 'vestir', 'colores', 'zapatos'],
        'pedido' => ['pedido', 'seguimiento', 'factura', 'cancelar', 'devolucion', 'token'],
        'pago' => ['pago', 'pagos', 'contra entrega', 'recoger', 'tienda'],
        'cuidado' => ['lavar', 'cuidar', 'material', 'prenda', 'mancha']
    ];

    foreach ($map as $group => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($normalized, $keyword) === false) {
                continue;
            }

            if ($group === 'envios') {
                return ['Bogota', 'Otras ciudades', 'Seguimiento', 'Metodos de pago'];
            }

            if ($group === 'tallas') {
                return ['Guia de tallas', 'Fit regular o slim', 'Arma un outfit', 'WhatsApp'];
            }

            if ($group === 'estilo') {
                return ['Outfit casual elegante', 'Look para oficina', 'Que zapatos combinar', 'Colores que combinan'];
            }

            if ($group === 'pedido') {
                return ['Consultar pedido con token', 'Facturas', 'Cambios o cancelaciones', 'WhatsApp'];
            }

            if ($group === 'pago') {
                return ['Contra entrega', 'Recoger en tienda', 'Envios', 'Facturas'];
            }

            if ($group === 'cuidado') {
                return ['Cuidado de prendas', 'Algodon premium', 'Como combinar colores', 'Arma un outfit'];
            }
        }
    }

    return tauroChatbotDefaultSuggestions();
}

function tauroChatbotFallbackReply(string $message): array
{
    $bestIntent = null;
    $bestScore = 0;

    foreach (tauroChatbotKnowledgeBase() as $intent) {
        $score = tauroChatbotScoreIntent($message, $intent);

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestIntent = $intent;
        }
    }

    if ($bestIntent !== null && $bestScore > 0) {
        return [
            'mode' => 'fallback',
            'text' => (string) ($bestIntent['answer']['text'] ?? ''),
            'suggestions' => (array) ($bestIntent['answer']['suggestions'] ?? tauroChatbotSuggestPrompts($message))
        ];
    }

    return [
        'mode' => 'fallback',
        'text' => 'Puedo ayudarte con dudas de la tienda, estilo masculino, combinaciones, cuidado de prendas, tallas, envios, compras y seguimiento publico con token. Si quieres una respuesta mas afinada, cuentame la ocasion, la prenda, el color o la referencia exacta que quieres revisar.',
        'suggestions' => tauroChatbotSuggestPrompts($message)
    ];
}

function tauroChatbotCheckRateLimit(int $maxRequests = 12, int $windowSeconds = 180): array
{
    if (!isset($_SESSION['tauro_chatbot_hits']) || !is_array($_SESSION['tauro_chatbot_hits'])) {
        $_SESSION['tauro_chatbot_hits'] = [];
    }

    $now = time();
    $hits = [];

    foreach ($_SESSION['tauro_chatbot_hits'] as $timestamp) {
        $safeTimestamp = (int) $timestamp;

        if ($safeTimestamp >= ($now - $windowSeconds)) {
            $hits[] = $safeTimestamp;
        }
    }

    if (count($hits) >= $maxRequests) {
        sort($hits);

        return [
            'allowed' => false,
            'retry_after' => max(1, $windowSeconds - ($now - (int) $hits[0]))
        ];
    }

    $hits[] = $now;
    $_SESSION['tauro_chatbot_hits'] = $hits;

    return [
        'allowed' => true,
        'retry_after' => 0
    ];
}

function tauroChatbotResetConversation(): void
{
    unset($_SESSION['tauro_chatbot_previous_response_id']);
}

function tauroChatbotExtractKeywords(string $message): array
{
    $normalized = tauroChatbotNormalizeText($message);

    if ($normalized === '') {
        return [];
    }

    $words = preg_split('/\s+/', $normalized) ?: [];
    $stopWords = array_flip([
        'de', 'la', 'el', 'los', 'las', 'un', 'una', 'unos', 'unas', 'para', 'por',
        'con', 'sin', 'que', 'como', 'quiero', 'busco', 'necesito', 'algo', 'mas',
        'menos', 'del', 'al', 'se', 'me', 'mi', 'tu', 'su', 'y', 'o', 'en', 'lo',
        'le', 'les', 'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas'
    ]);

    $keywords = [];

    foreach ($words as $word) {
        if ($word === '' || strlen($word) < 3 || isset($stopWords[$word])) {
            continue;
        }

        $keywords[$word] = true;
    }

    $phrases = [
        'chaqueta negra',
        'chaquetas negras',
        'camiseta blanca',
        'camisetas blancas',
        'pantalon slim',
        'casual elegante',
        'look oficina',
        'fit regular',
        'fit slim'
    ];

    foreach ($phrases as $phrase) {
        if (strpos($normalized, $phrase) !== false) {
            $keywords[$phrase] = true;
        }
    }

    return array_keys($keywords);
}

function tauroChatbotLooksLikeProductIntent(string $message): bool
{
    $normalized = tauroChatbotNormalizeText($message);

    if ($normalized === '') {
        return false;
    }

    $signals = [
        'producto', 'productos', 'prenda', 'prendas', 'ropa', 'outfit', 'look',
        'combinar', 'vestir', 'regalo', 'chaqueta', 'chaquetas', 'camiseta',
        'camisetas', 'polo', 'polos', 'pantalon', 'pantalones', 'jean', 'jeans',
        'tenis', 'zapatos', 'calzado', 'accesorio', 'accesorios', 'fit', 'material',
        'negro', 'negra', 'blanco', 'blanca', 'azul', 'gris', 'arena', 'recomiend'
    ];

    foreach ($signals as $signal) {
        if (strpos($normalized, $signal) !== false) {
            return true;
        }
    }

    return false;
}

function tauroChatbotFetchCatalog(PDO $conn, int $limit = 160): array
{
    $sql = "
      SELECT
        p.id,
        p.nombre,
        p.descripcion,
        p.precio,
        p.categoria,
        p.sku,
        p.marca,
        p.color,
        p.material,
        p.fit,
        p.imagen,
        COALESCE(SUM(pt.stock), 0) AS stock_total
      FROM productos p
      LEFT JOIN producto_tallas pt ON pt.producto_id = p.id
      GROUP BY
        p.id,
        p.nombre,
        p.descripcion,
        p.precio,
        p.categoria,
        p.sku,
        p.marca,
        p.color,
        p.material,
        p.fit,
        p.imagen
      ORDER BY p.id DESC
      LIMIT " . max(1, (int) $limit);

    $stmt = $conn->query($sql);

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function tauroChatbotScoreProduct(array $product, string $normalizedMessage, array $keywords): int
{
    $name = tauroChatbotNormalizeText((string) ($product['nombre'] ?? ''));
    $description = tauroChatbotNormalizeText((string) ($product['descripcion'] ?? ''));
    $category = tauroChatbotNormalizeText((string) ($product['categoria'] ?? ''));
    $color = tauroChatbotNormalizeText((string) ($product['color'] ?? ''));
    $fit = tauroChatbotNormalizeText((string) ($product['fit'] ?? ''));
    $material = tauroChatbotNormalizeText((string) ($product['material'] ?? ''));

    $score = 0;

    if ($normalizedMessage !== '' && strpos($name, $normalizedMessage) !== false) {
        $score += 12;
    }

    foreach ($keywords as $keyword) {
        if ($keyword === '') {
            continue;
        }

        if (strpos($name, $keyword) !== false) {
            $score += strpos($keyword, ' ') !== false ? 8 : 5;
        }

        if ($category !== '' && strpos($category, $keyword) !== false) {
            $score += 5;
        }

        if ($color !== '' && strpos($color, $keyword) !== false) {
            $score += 4;
        }

        if ($fit !== '' && strpos($fit, $keyword) !== false) {
            $score += 4;
        }

        if ($material !== '' && strpos($material, $keyword) !== false) {
            $score += 3;
        }

        if ($description !== '' && strpos($description, $keyword) !== false) {
            $score += 2;
        }
    }

    if (strpos($normalizedMessage, 'negro') !== false && strpos($color, 'negro') !== false) {
        $score += 4;
    }

    if (strpos($normalizedMessage, 'blanco') !== false && strpos($color, 'blanco') !== false) {
        $score += 4;
    }

    if (strpos($normalizedMessage, 'azul') !== false && strpos($color, 'azul') !== false) {
        $score += 4;
    }

    if ((int) ($product['stock_total'] ?? 0) > 0) {
        $score += 2;
    } else {
        $score -= 4;
    }

    return $score;
}

function tauroChatbotFindRelevantProducts(PDO $conn, string $message, int $limit = 4): array
{
    $catalog = tauroChatbotFetchCatalog($conn);
    $normalizedMessage = tauroChatbotNormalizeText($message);
    $keywords = tauroChatbotExtractKeywords($message);
    $scored = [];

    foreach ($catalog as $product) {
        $score = tauroChatbotScoreProduct($product, $normalizedMessage, $keywords);

        if ($score <= 0 && !tauroChatbotLooksLikeProductIntent($message)) {
            continue;
        }

        $product['score'] = $score;
        $scored[] = $product;
    }

    usort($scored, static function (array $a, array $b): int {
        $scoreComparison = (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);

        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        $stockComparison = (int) ($b['stock_total'] ?? 0) <=> (int) ($a['stock_total'] ?? 0);

        if ($stockComparison !== 0) {
            return $stockComparison;
        }

        return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });

    $selected = array_slice(array_values(array_filter($scored, static function (array $product): bool {
        return (int) ($product['score'] ?? 0) > 0;
    })), 0, $limit);

    if ($selected) {
        return $selected;
    }

    if (!tauroChatbotLooksLikeProductIntent($message)) {
        return [];
    }

    return array_slice($scored, 0, $limit);
}

function tauroChatbotBuildCatalogContext(PDO $conn, string $message): array
{
    $products = tauroChatbotFindRelevantProducts($conn, $message, 4);

    if (!$products) {
        return ['products' => [], 'text' => ''];
    }

    $lines = [
        'Catalogo real relevante para esta consulta. Si recomiendas productos, usa solo estos datos como base actual:'
    ];

    foreach ($products as $product) {
        $parts = [
            '#' . (int) ($product['id'] ?? 0),
            trim((string) ($product['nombre'] ?? 'Producto')),
            tauroChatbotFormatPrice((float) ($product['precio'] ?? 0))
        ];

        $category = trim((string) ($product['categoria'] ?? ''));
        $color = trim((string) ($product['color'] ?? ''));
        $fit = trim((string) ($product['fit'] ?? ''));
        $material = trim((string) ($product['material'] ?? ''));

        if ($category !== '') {
            $parts[] = 'Categoria: ' . $category;
        }

        if ($color !== '') {
            $parts[] = 'Color: ' . $color;
        }

        if ($fit !== '') {
            $parts[] = 'Fit: ' . $fit;
        }

        if ($material !== '') {
            $parts[] = 'Material: ' . $material;
        }

        $parts[] = ((int) ($product['stock_total'] ?? 0) > 0) ? 'Stock disponible' : 'Stock por confirmar';
        $parts[] = 'URL: ' . tauroChatbotAbsoluteUrl('producto.php?id=' . (int) ($product['id'] ?? 0));

        $lines[] = '- ' . implode(' | ', $parts);
    }

    return [
        'products' => $products,
        'text' => implode("\n", $lines)
    ];
}

function tauroChatbotBuildCatalogReplyFromProducts(string $message, array $products): array
{
    $normalizedMessage = tauroChatbotNormalizeText($message);
    $lines = [];

    if (strpos($normalizedMessage, 'outfit') !== false || strpos($normalizedMessage, 'look') !== false) {
        $lines[] = 'Te propongo estas opciones reales del catalogo para arrancar ese look:';
    } else {
        $lines[] = 'Encontre estas opciones reales del catalogo que encajan con lo que buscas:';
    }

    foreach ($products as $product) {
        $parts = [
            '- ' . trim((string) ($product['nombre'] ?? 'Producto')),
            tauroChatbotFormatPrice((float) ($product['precio'] ?? 0))
        ];

        $category = trim((string) ($product['categoria'] ?? ''));
        $color = trim((string) ($product['color'] ?? ''));
        $fit = trim((string) ($product['fit'] ?? ''));

        if ($category !== '') {
            $parts[] = $category;
        }

        if ($color !== '') {
            $parts[] = $color;
        }

        if ($fit !== '') {
            $parts[] = 'Fit ' . $fit;
        }

        if ((int) ($product['stock_total'] ?? 0) > 0) {
            $parts[] = 'Disponible';
        }

        $parts[] = tauroChatbotAbsoluteUrl('producto.php?id=' . (int) ($product['id'] ?? 0));
        $lines[] = implode(' | ', $parts);
    }

    $lines[] = '';
    $lines[] = 'Si quieres, te afino la recomendacion por ocasion, color, presupuesto o tipo de fit.';

    return [
        'mode' => 'catalog',
        'text' => implode("\n", $lines),
        'suggestions' => ['Arma un outfit', 'Guia de tallas', 'Look para oficina', 'Que zapatos combinan']
    ];
}

function tauroChatbotExtractPublicToken(string $message): string
{
    if (preg_match('/factura_publica\.php\?token=([a-f0-9]{32,64})/i', $message, $match)) {
        return strtolower((string) $match[1]);
    }

    if (preg_match('/\b([a-f0-9]{32,64})\b/i', $message, $match)) {
        return strtolower((string) $match[1]);
    }

    return '';
}

function tauroChatbotExtractShortPublicToken(string $message): array
{
    if (preg_match('/\b([a-f0-9]{6,24})\s*\.\.\.\s*([a-f0-9]{6,24})\b/i', $message, $match)) {
        return [
            'prefix' => strtolower((string) $match[1]),
            'suffix' => strtolower((string) $match[2]),
        ];
    }

    return [];
}

function tauroChatbotExtractOrderId(string $message): int
{
    if (preg_match('/(?:pedido|orden|compra|factura)\s*#?\s*(\d{1,10})/iu', $message, $match)) {
        return (int) $match[1];
    }

    if (preg_match('/#\s*(\d{1,10})\b/', $message, $match)) {
        return (int) $match[1];
    }

    return 0;
}

function tauroChatbotLooksLikeSpecificOrderQuery(string $message): bool
{
    $normalized = tauroChatbotNormalizeText($message);

    if ($normalized === '') {
        return false;
    }

    if (
        tauroChatbotExtractPublicToken($message) !== '' ||
        tauroChatbotExtractShortPublicToken($message) !== [] ||
        tauroChatbotExtractOrderId($message) > 0
    ) {
        return true;
    }

    foreach (['mi pedido', 'mi orden', 'mi factura', 'tracking', 'seguimiento', 'estado de mi pedido'] as $signal) {
        if (strpos($normalized, $signal) !== false) {
            return true;
        }
    }

    return false;
}

function tauroChatbotBuildNeedTokenReply(int $orderId = 0): array
{
    $lines = [];

    if ($orderId > 0) {
        $lines[] = 'Para revisar el pedido #' . $orderId . ' desde este chat publico necesito tambien el token publico de la factura o el enlace de verificacion.';
    } else {
        $lines[] = 'Para revisar un pedido desde este chat publico necesito el token publico de la factura o el enlace de verificacion.';
    }

    $lines[] = 'Con solo el numero de pedido no puedo exponer informacion privada.';
    $lines[] = 'Puedes pegar aqui el token completo, el token abreviado tipo abc123...789xyz o el enlace de factura_publica.php?token=...';

    return [
        'mode' => 'order_guard',
        'text' => implode("\n", $lines),
        'suggestions' => ['Consultar pedido con token', 'Facturas', 'Cambios o cancelaciones', 'WhatsApp']
    ];
}

function tauroChatbotFetchPublicOrder(PDO $conn, string $token, int $orderId = 0): ?array
{
    $sql = "
      SELECT
        p.id,
        p.fecha,
        p.estado,
        p.total,
        p.subtotal_productos,
        p.costo_envio,
        p.metodo_pago,
        p.ciudad_envio,
        p.zona_envio,
        p.dias_entrega_min,
        p.dias_entrega_max,
        p.factura_token,
        (
          SELECT MAX(h.fecha)
          FROM pedido_estados_historial h
          WHERE h.pedido_id = p.id
        ) AS ultima_actualizacion
      FROM pedidos p
      WHERE p.factura_token = ?
    ";

    $params = [$token];

    if ($orderId > 0) {
        $sql .= ' AND p.id = ?';
        $params[] = $orderId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    return $pedido ?: null;
}

function tauroChatbotFetchPublicOrderByShortToken(PDO $conn, string $prefix, string $suffix, int $orderId = 0): array
{
    if ($prefix === '' || $suffix === '' || strlen($prefix) < 6 || strlen($suffix) < 6) {
        return ['status' => 'invalid', 'pedido' => null];
    }

    $sql = "
      SELECT
        p.id,
        p.fecha,
        p.estado,
        p.total,
        p.subtotal_productos,
        p.costo_envio,
        p.metodo_pago,
        p.ciudad_envio,
        p.zona_envio,
        p.dias_entrega_min,
        p.dias_entrega_max,
        p.factura_token,
        (
          SELECT MAX(h.fecha)
          FROM pedido_estados_historial h
          WHERE h.pedido_id = p.id
        ) AS ultima_actualizacion
      FROM pedidos p
      WHERE p.factura_token LIKE ?
        AND p.factura_token LIKE ?
    ";

    $params = [$prefix . '%', '%' . $suffix];

    if ($orderId > 0) {
        $sql .= ' AND p.id = ?';
        $params[] = $orderId;
    }

    $sql .= ' LIMIT 2';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return ['status' => 'not_found', 'pedido' => null];
    }

    if (count($rows) > 1) {
        return ['status' => 'ambiguous', 'pedido' => null];
    }

    return ['status' => 'ok', 'pedido' => $rows[0]];
}

function tauroChatbotBuildPublicOrderLines(array $pedido, string $token): array
{
    $estadoKey = normalizarTextoPedido((string) ($pedido['estado'] ?? ''));
    $estadoLabel = etiquetasEstadoPedido()[$estadoKey] ?? ucfirst((string) ($pedido['estado'] ?? 'Pendiente'));
    $invoiceUrl = tauroChatbotAbsoluteUrl('factura_publica.php?token=' . rawurlencode($token));
    $lines = [
        'Encontre un pedido publico que coincide con tu referencia.',
        'Pedido #' . (int) ($pedido['id'] ?? 0),
        'Estado actual: ' . $estadoLabel,
        'Fecha del pedido: ' . tauroChatbotFormatDate((string) ($pedido['fecha'] ?? '')),
        'Ultima actualizacion: ' . tauroChatbotFormatDate((string) ($pedido['ultima_actualizacion'] ?? '')),
        'Total: ' . tauroChatbotFormatPrice((float) ($pedido['total'] ?? 0)),
        'Metodo de pago: ' . ucfirst((string) ($pedido['metodo_pago'] ?? 'No definido'))
    ];

    $city = trim((string) ($pedido['ciudad_envio'] ?? ''));
    $zone = trim((string) ($pedido['zona_envio'] ?? ''));

    if ($city !== '' || $zone !== '') {
        $cityLabel = $city !== '' ? textoTituloPedido($city) : 'No definida';
        $zoneLabel = $zone !== '' ? textoTituloPedido($zone) : 'No definida';
        $lines[] = 'Ciudad/Zona: ' . $cityLabel . ' / ' . $zoneLabel;
    }

    if (!empty($pedido['dias_entrega_min']) && !empty($pedido['dias_entrega_max'])) {
        $lines[] = 'Entrega estimada: ' . (int) $pedido['dias_entrega_min'] . ' - ' . (int) $pedido['dias_entrega_max'] . ' dias';
    }

    $lines[] = 'Factura publica: ' . $invoiceUrl;
    $lines[] = 'Si necesitas ayuda humana con este pedido, tambien puedes escribir a WhatsApp al +57 302 334 1713.';

    return $lines;
}

function tauroChatbotBuildPublicOrderReply(PDO $conn, string $message): ?array
{
    $token = tauroChatbotExtractPublicToken($message);
    $shortToken = tauroChatbotExtractShortPublicToken($message);
    $orderId = tauroChatbotExtractOrderId($message);

    if ($token === '' && $shortToken === []) {
        if (tauroChatbotLooksLikeSpecificOrderQuery($message) && $orderId > 0) {
            return tauroChatbotBuildNeedTokenReply($orderId);
        }

        return null;
    }

    if ($token !== '' && !preg_match('/^[a-f0-9]{32,64}$/', $token)) {
        return [
            'mode' => 'order_invalid_token',
            'text' => 'El token publico que compartiste no tiene un formato valido. Puedes pegar el token hexadecimal o el enlace completo de la factura publica.',
            'suggestions' => ['Consultar pedido con token', 'Facturas', 'WhatsApp', 'Contacto']
        ];
    }

    $pedido = null;
    $resolvedToken = $token;

    if ($token !== '') {
        $pedido = tauroChatbotFetchPublicOrder($conn, $token, $orderId);
    } elseif ($shortToken !== []) {
        $lookup = tauroChatbotFetchPublicOrderByShortToken(
            $conn,
            (string) ($shortToken['prefix'] ?? ''),
            (string) ($shortToken['suffix'] ?? ''),
            $orderId
        );

        if ($lookup['status'] === 'ambiguous') {
            return [
                'mode' => 'order_ambiguous_token',
                'text' => 'Ese token abreviado coincide con mas de un pedido. Pegame el token completo o el enlace publico de la factura para identificarlo sin riesgo.',
                'suggestions' => ['Consultar pedido con token', 'Facturas', 'WhatsApp', 'Contacto']
            ];
        }

        if ($lookup['status'] === 'invalid') {
            return [
                'mode' => 'order_invalid_token',
                'text' => 'El token abreviado que compartiste es demasiado corto. Necesito mas caracteres o, idealmente, el token completo o el enlace de factura publica.',
                'suggestions' => ['Consultar pedido con token', 'Facturas', 'WhatsApp', 'Contacto']
            ];
        }

        $pedido = $lookup['pedido'];
        $resolvedToken = is_array($pedido) ? (string) ($pedido['factura_token'] ?? '') : '';
    }

    if (!$pedido) {
        return [
            'mode' => 'order_not_found',
            'text' => 'No encontre un pedido publico que coincida con esa referencia. Revisa el token, pega el enlace completo de la factura o comparte mas caracteres del token.',
            'suggestions' => ['Consultar pedido con token', 'Facturas', 'WhatsApp', 'Contacto']
        ];
    }

    $lines = tauroChatbotBuildPublicOrderLines($pedido, $resolvedToken);

    return [
        'mode' => 'public_order',
        'text' => implode("\n", $lines),
        'suggestions' => ['Cambios o cancelaciones', 'Facturas', 'Seguimiento', 'WhatsApp']
    ];
}

function tauroChatbotBuildInstructions(string $catalogContext = ''): string
{
    $categories = [
        'Camisetas y Polos',
        'Chaquetas y Buzos',
        'Pantalones',
        'Calzado',
        'Accesorios'
    ];

    $shippingCities = [
        'Bogota',
        'Medellin',
        'Cali',
        'Barranquilla',
        'otras ciudades con tarifa estandar'
    ];

    $lines = [
        'Eres Tauro Concierge, el asistente virtual de Tauro Store.',
        'Tauro Store es una tienda de ropa masculina con enfoque elegante, sobrio, seguro y contemporaneo en Colombia.',
        'Responde siempre en espanol claro, natural y cercano.',
        'Tu trabajo es ayudar con dudas sobre la tienda y tambien con preguntas mas abiertas del cliente, incluyendo estilo masculino, combinaciones, regalos, outfit para ocasiones, cuidado de prendas, materiales y preguntas generales.',
        'Si la pregunta no es sobre la tienda, igual puedes responder con normalidad y de forma util.',
        'No afirmes que puedes ver pedidos, pagos, direcciones, stock exacto, cuentas ni datos personales desde este chat publico.',
        'Si piden datos privados o seguimiento de un pedido puntual, explica con claridad que deben usar su token publico de factura, iniciar sesion o escribir a WhatsApp al +57 302 334 1713.',
        'No inventes precios exactos, tiempos exactos, promociones activas ni disponibilidad exacta cuando no la conozcas.',
        'Si recibes contexto real de catalogo, usalo como fuente para recomendar productos y no inventes articulos adicionales.',
        'Si no tienes un dato exacto, dilo con honestidad y redirige a la pagina del producto, checkout o canal humano segun corresponda.',
        'Manten las respuestas breves pero utiles: preferiblemente entre 2 y 4 parrafos cortos o un maximo de 4 bullets.',
        'Si es oportuno, termina con una pregunta corta de seguimiento.',
        'Categorias frecuentes de la tienda: ' . implode(', ', $categories) . '.',
        'Ciudades de envio frecuentes: ' . implode(', ', $shippingCities) . '.'
    ];

    if ($catalogContext !== '') {
        $lines[] = '';
        $lines[] = $catalogContext;
    }

    return implode("\n", $lines);
}

function tauroChatbotExtractText(array $response): string
{
    if (!empty($response['output_text']) && is_string($response['output_text'])) {
        return trim($response['output_text']);
    }

    $chunks = [];

    foreach (($response['output'] ?? []) as $item) {
        if (($item['type'] ?? '') !== 'message') {
            continue;
        }

        foreach (($item['content'] ?? []) as $contentItem) {
            if (!is_array($contentItem)) {
                continue;
            }

            if (
                isset($contentItem['text']) &&
                is_string($contentItem['text']) &&
                trim($contentItem['text']) !== ''
            ) {
                $chunks[] = trim($contentItem['text']);
            }
        }
    }

    return trim(implode("\n\n", $chunks));
}

function tauroChatbotSendOpenAI(array $payload, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'cURL no disponible'];
    }

    $ch = curl_init('https://api.openai.com/v1/responses');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($rawResponse) || $rawResponse === '') {
        return ['ok' => false, 'status' => $statusCode, 'data' => null, 'error' => $curlError !== '' ? $curlError : 'Respuesta vacia'];
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $statusCode, 'data' => null, 'error' => 'JSON invalido'];
    }

    return [
        'ok' => $statusCode >= 200 && $statusCode < 300,
        'status' => $statusCode,
        'data' => $decoded,
        'error' => (string) (($decoded['error']['message'] ?? '') ?: $curlError)
    ];
}

function tauroChatbotAiReply(string $message, string $catalogContext = ''): ?array
{
    $apiKey = tauroChatbotEnv('OPENAI_API_KEY');

    if ($apiKey === '') {
        return null;
    }

    $model = tauroChatbotEnv('OPENAI_CHATBOT_MODEL', 'gpt-5.4-mini');
    $reasoningEffort = tauroChatbotEnv('OPENAI_CHATBOT_REASONING', 'low');
    $payload = [
        'model' => $model,
        'instructions' => tauroChatbotBuildInstructions($catalogContext),
        'input' => $message,
        'max_output_tokens' => 340
    ];

    if (strpos($model, 'gpt-5') === 0) {
        $payload['reasoning'] = ['effort' => $reasoningEffort];
    }

    $previousResponseId = $_SESSION['tauro_chatbot_previous_response_id'] ?? '';

    if (is_string($previousResponseId) && trim($previousResponseId) !== '') {
        $payload['previous_response_id'] = trim($previousResponseId);
    }

    $result = tauroChatbotSendOpenAI($payload, $apiKey);

    if (!$result['ok'] && isset($payload['previous_response_id'])) {
        unset($payload['previous_response_id']);
        tauroChatbotResetConversation();
        $result = tauroChatbotSendOpenAI($payload, $apiKey);
    }

    if (!$result['ok'] || !is_array($result['data'])) {
        return null;
    }

    $text = tauroChatbotExtractText($result['data']);

    if ($text === '') {
        return null;
    }

    if (!empty($result['data']['id']) && is_string($result['data']['id'])) {
        $_SESSION['tauro_chatbot_previous_response_id'] = trim($result['data']['id']);
    }

    return [
        'mode' => 'ai',
        'text' => $text,
        'suggestions' => tauroChatbotSuggestPrompts($message)
    ];
}

function tauroChatbotBuildReply(PDO $conn, string $message): array
{
    $publicOrderReply = tauroChatbotBuildPublicOrderReply($conn, $message);

    if ($publicOrderReply !== null) {
        return $publicOrderReply;
    }

    $catalogContext = tauroChatbotBuildCatalogContext($conn, $message);
    $aiReply = tauroChatbotAiReply($message, (string) ($catalogContext['text'] ?? ''));

    if ($aiReply !== null) {
        return $aiReply;
    }

    if (!empty($catalogContext['products']) && tauroChatbotLooksLikeProductIntent($message)) {
        return tauroChatbotBuildCatalogReplyFromProducts($message, (array) $catalogContext['products']);
    }

    return tauroChatbotFallbackReply($message);
}
