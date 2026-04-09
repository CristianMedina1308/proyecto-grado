<?php

/**
 * Normaliza texto para comparaciones (ciudad/zona/estado).
 */
function normalizarTextoPedido(string $valor): string
{
    $valor = trim($valor);
    if ($valor === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        $valor = mb_strtolower($valor, 'UTF-8');
    } else {
        $valor = strtolower($valor);
    }

    if (function_exists('iconv')) {
        $convertido = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        if ($convertido !== false) {
            $valor = strtolower($convertido);
        }
    }

    $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor);
    return trim($valor ?? '');
}

/**
 * Convierte un slug simple en texto legible.
 */
function textoTituloPedido(?string $valor): string
{
    $limpio = normalizarTextoPedido((string) $valor);
    if ($limpio === '') {
        return 'No definido';
    }

    return ucwords($limpio);
}

/**
 * Lista de estados válidos para pedidos.
 */
function estadosPedidoPermitidos(): array
{
    return ['pendiente', 'pagado', 'preparando', 'enviado', 'entregado', 'cancelado'];
}

/**
 * Etiquetas de estados para UI.
 */
function etiquetasEstadoPedido(): array
{
    return [
        'pendiente' => 'Pendiente',
        'pagado' => 'Pagado',
        'preparando' => 'Preparando',
        'enviado' => 'Enviado',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado'
    ];
}

/**
 * Define transición válida de estados.
 */
function puedeTransicionarEstadoPedido(string $estadoActual, string $nuevoEstado): bool
{
    $estadoActual = normalizarTextoPedido($estadoActual);
    $nuevoEstado = normalizarTextoPedido($nuevoEstado);

    if (!in_array($estadoActual, estadosPedidoPermitidos(), true) || !in_array($nuevoEstado, estadosPedidoPermitidos(), true)) {
        return false;
    }

    if ($estadoActual === $nuevoEstado) {
        return true;
    }

    $transiciones = [
        'pendiente' => ['pagado', 'cancelado'],
        'pagado' => ['preparando', 'cancelado'],
        'preparando' => ['enviado'],
        'enviado' => ['entregado'],
        'entregado' => [],
        'cancelado' => []
    ];

    return in_array($nuevoEstado, $transiciones[$estadoActual] ?? [], true);
}

/**
 * Retorna los estados disponibles desde el estado actual (incluye actual).
 */
function opcionesEstadoPedido(string $estadoActual): array
{
    $estadoActual = normalizarTextoPedido($estadoActual);
    $base = [$estadoActual];

    $transiciones = [
        'pendiente' => ['pagado', 'cancelado'],
        'pagado' => ['preparando', 'cancelado'],
        'preparando' => ['enviado'],
        'enviado' => ['entregado'],
        'entregado' => [],
        'cancelado' => []
    ];

    foreach (($transiciones[$estadoActual] ?? []) as $estado) {
        $base[] = $estado;
    }

    return array_values(array_unique(array_filter($base)));
}

/**
 * Relación estado -> columna de fecha.
 */
function columnaFechaEstadoPedido(string $estado): ?string
{
    $estado = normalizarTextoPedido($estado);
    $mapa = [
        'pendiente' => 'estado_pendiente_at',
        'pagado' => 'estado_pagado_at',
        'preparando' => 'estado_preparando_at',
        'enviado' => 'estado_enviado_at',
        'entregado' => 'estado_entregado_at',
        'cancelado' => 'estado_cancelado_at'
    ];

    return $mapa[$estado] ?? null;
}

/**
 * Obtiene tarifa de envío por ciudad/zona con fallback.
 */
function obtenerTarifaEnvio(PDO $conn, ?string $ciudad, ?string $zona): ?array
{
    $ciudadNorm = normalizarTextoPedido((string) $ciudad);
    $zonaNorm = normalizarTextoPedido((string) $zona);

    if ($ciudadNorm === '') {
        return null;
    }

    if ($zonaNorm === '') {
        $zonaNorm = 'estandar';
    }

    $buscar = $conn->prepare("
      SELECT ciudad, zona, costo, dias_min, dias_max
      FROM tarifas_envio
      WHERE activo = 1 AND ciudad = ? AND zona = ?
      LIMIT 1
    ");

    $buscar->execute([$ciudadNorm, $zonaNorm]);
    $tarifa = $buscar->fetch(PDO::FETCH_ASSOC);

    if (!$tarifa && $zonaNorm !== 'estandar') {
        $buscar->execute([$ciudadNorm, 'estandar']);
        $tarifa = $buscar->fetch(PDO::FETCH_ASSOC);
    }

    if (!$tarifa) {
        $buscar->execute(['otras', 'estandar']);
        $tarifa = $buscar->fetch(PDO::FETCH_ASSOC);
    }

    if (!$tarifa) {
        return null;
    }

    return [
        'ciudad' => (string) $tarifa['ciudad'],
        'zona' => (string) $tarifa['zona'],
        'costo' => (float) $tarifa['costo'],
        'dias_min' => (int) $tarifa['dias_min'],
        'dias_max' => (int) $tarifa['dias_max']
    ];
}

/**
 * Registra cambio de estado en historial.
 */
function registrarHistorialEstadoPedido(PDO $conn, int $pedidoId, string $estado, ?int $usuarioId, string $origen, ?string $nota): void
{
    $insert = $conn->prepare("
      INSERT INTO pedido_estados_historial (pedido_id, estado, usuario_id, origen, nota)
      VALUES (?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $pedidoId,
        normalizarTextoPedido($estado),
        $usuarioId,
        normalizarTextoPedido($origen) ?: 'sistema',
        $nota
    ]);
}

/**
 * Reintegra inventario por talla cuando se cancela un pedido.
 */
function reintegrarStockPedido(PDO $conn, int $pedidoId): void
{
    $detalleStmt = $conn->prepare("
      SELECT producto_id, talla, cantidad
      FROM detalle_pedido
      WHERE pedido_id = ?
    ");
    $detalleStmt->execute([$pedidoId]);
    $detalles = $detalleStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$detalles) {
        $marcar = $conn->prepare("UPDATE pedidos SET stock_reintegrado = 1 WHERE id = ?");
        $marcar->execute([$pedidoId]);
        return;
    }

    $buscarProducto = $conn->prepare("SELECT id FROM productos WHERE id = ? LIMIT 1");
    $buscarTalla = $conn->prepare("SELECT id FROM tallas WHERE nombre = ? LIMIT 1");
    $buscarRelacion = $conn->prepare("
      SELECT id
      FROM producto_tallas
      WHERE producto_id = ? AND talla_id = ?
      LIMIT 1
      FOR UPDATE
    ");
    $actualizarStock = $conn->prepare("UPDATE producto_tallas SET stock = stock + ? WHERE id = ?");
    $insertarRelacion = $conn->prepare("
      INSERT INTO producto_tallas (producto_id, talla_id, stock)
      VALUES (?, ?, ?)
    ");

    foreach ($detalles as $linea) {
        $productoId = (int) ($linea['producto_id'] ?? 0);
        $cantidad = (int) ($linea['cantidad'] ?? 0);
        $tallaNombre = trim((string) ($linea['talla'] ?? ''));

        if ($productoId <= 0 || $cantidad <= 0 || $tallaNombre === '') {
            continue;
        }

        $buscarProducto->execute([$productoId]);
        if (!$buscarProducto->fetchColumn()) {
            continue;
        }

        $buscarTalla->execute([$tallaNombre]);
        $tallaId = (int) $buscarTalla->fetchColumn();
        if ($tallaId <= 0) {
            continue;
        }

        $buscarRelacion->execute([$productoId, $tallaId]);
        $relacionId = (int) $buscarRelacion->fetchColumn();

        if ($relacionId > 0) {
            $actualizarStock->execute([$cantidad, $relacionId]);
        } else {
            $insertarRelacion->execute([$productoId, $tallaId, $cantidad]);
        }
    }

    $marcar = $conn->prepare("UPDATE pedidos SET stock_reintegrado = 1 WHERE id = ?");
    $marcar->execute([$pedidoId]);
}

/**
 * Cambia estado de pedido con validación de transición, timestamps, historial y reintegro.
 */
function actualizarEstadoPedido(PDO $conn, int $pedidoId, string $nuevoEstado, ?int $usuarioId, string $origen = 'sistema', ?string $nota = null): array
{
    $nuevoEstado = normalizarTextoPedido($nuevoEstado);

    if (!in_array($nuevoEstado, estadosPedidoPermitidos(), true)) {
        return ['ok' => false, 'mensaje' => 'Estado no valido.'];
    }

    try {
        $conn->beginTransaction();

        $pedidoStmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ? FOR UPDATE");
        $pedidoStmt->execute([$pedidoId]);
        $pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            $conn->rollBack();
            return ['ok' => false, 'mensaje' => 'Pedido no encontrado.'];
        }

        $estadoActual = normalizarTextoPedido((string) ($pedido['estado'] ?? 'pendiente'));

        if (!puedeTransicionarEstadoPedido($estadoActual, $nuevoEstado)) {
            $conn->rollBack();
            return [
                'ok' => false,
                'mensaje' => 'No se puede cambiar de "' . ($estadoActual ?: 'desconocido') . '" a "' . $nuevoEstado . '".'
            ];
        }

        if ($estadoActual === $nuevoEstado) {
            $conn->commit();
            return ['ok' => true, 'mensaje' => 'El pedido ya estaba en ese estado.'];
        }

        if ($nuevoEstado === 'cancelado' && (int) ($pedido['stock_reintegrado'] ?? 0) === 0) {
            reintegrarStockPedido($conn, $pedidoId);
        }

        $columnaFecha = columnaFechaEstadoPedido($nuevoEstado);
        if ($columnaFecha) {
            $sql = "UPDATE pedidos SET estado = ?, {$columnaFecha} = COALESCE({$columnaFecha}, NOW()) WHERE id = ?";
            $update = $conn->prepare($sql);
            $update->execute([$nuevoEstado, $pedidoId]);
        } else {
            $update = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $update->execute([$nuevoEstado, $pedidoId]);
        }

        registrarHistorialEstadoPedido($conn, $pedidoId, $nuevoEstado, $usuarioId, $origen, $nota);
        $conn->commit();

        return ['ok' => true, 'mensaje' => 'Estado actualizado correctamente.'];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        return ['ok' => false, 'mensaje' => 'No se pudo actualizar el estado: ' . $e->getMessage()];
    }
}

