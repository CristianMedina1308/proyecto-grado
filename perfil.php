<?php
require_once 'includes/app.php';
if (!isset($_SESSION['usuario'])) {
  header("Location: login.php");
  exit;
}

include 'includes/conexion.php';
include 'includes/pedidos_utils.php';

$usuarioId = (int) $_SESSION['usuario']['id'];
$usuario = $_SESSION['usuario'];
$error = '';
$etiquetasEstado = etiquetasEstadoPedido();

$pinDisponible = appDbHasColumn($conn, 'usuarios', 'recovery_pin_hash');
$pinConfigurado = false;
if ($pinDisponible) {
  try {
    $pinStmt = $conn->prepare('SELECT recovery_pin_hash FROM usuarios WHERE id = ?');
    $pinStmt->execute([$usuarioId]);
    $pinConfigurado = trim((string) $pinStmt->fetchColumn()) !== '';
  } catch (Throwable $e) {
    $pinConfigurado = false;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['actualizar_datos'])) {
    if (!appValidarCsrf('perfil_form', $_POST['csrf_token'] ?? null)) {
      $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
      $nuevoNombre = trim((string) ($_POST['nombre'] ?? ''));
      $nuevoEmail = trim((string) ($_POST['email'] ?? ''));

      if ($nuevoNombre !== '' && $nuevoEmail !== '') {
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$nuevoNombre, $nuevoEmail, $usuarioId])) {
          $_SESSION['usuario']['nombre'] = $nuevoNombre;
          $_SESSION['usuario']['email'] = $nuevoEmail;
          appFlash('success', 'Tus datos personales fueron actualizados.', 'Perfil actualizado');
          appRedirect('perfil.php');
        } else {
          $error = 'Error al actualizar datos.';
        }
      } else {
        $error = 'Todos los campos son obligatorios.';
      }
    }
  }

  if (isset($_POST['cambiar_contrasena'])) {
    if (!appValidarCsrf('perfil_password_form', $_POST['csrf_token'] ?? null)) {
      $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
      $actual = (string) ($_POST['contrasena_actual'] ?? '');
      $nueva = (string) ($_POST['nueva_contrasena'] ?? '');
      $confirmar = (string) ($_POST['confirmar_contrasena'] ?? '');

      if ($actual !== '' && $nueva !== '' && $confirmar !== '') {
        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $hash = (string) $stmt->fetchColumn();

        if (password_verify($actual, $hash)) {
          if ($nueva === $confirmar) {
            $nuevaHash = password_hash($nueva, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $upd->execute([$nuevaHash, $usuarioId]);
            appFlash('success', 'Tu contrasena fue actualizada.', 'Cambio realizado');
            appRedirect('perfil.php');
          } else {
            $error = 'Las nuevas contrasenas no coinciden.';
          }
        } else {
          $error = 'Contrasena actual incorrecta.';
        }
      } else {
        $error = 'Completa todos los campos para cambiar la contrasena.';
      }
    }
  }

  if (isset($_POST['actualizar_pin'])) {
    if (!$pinDisponible) {
      $error = 'El PIN de recuperacion aun no esta habilitado en la base de datos.';
    } elseif (!appValidarCsrf('perfil_pin_form', $_POST['csrf_token'] ?? null)) {
      $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
      $contrasenaActual = (string) ($_POST['contrasena_actual_pin'] ?? '');
      $pinNuevo = trim((string) ($_POST['pin_recuperacion'] ?? ''));
      $pinConfirmar = trim((string) ($_POST['pin_recuperacion_confirmar'] ?? ''));

      if ($contrasenaActual === '' || $pinNuevo === '' || $pinConfirmar === '') {
        $error = 'Completa todos los campos para actualizar el PIN.';
      } elseif (!appValidateRecoveryPin($pinNuevo)) {
        $error = 'El PIN debe tener exactamente 4 digitos.';
      } elseif ($pinNuevo !== $pinConfirmar) {
        $error = 'Los PIN no coinciden.';
      } elseif (appIsWeakRecoveryPin($pinNuevo)) {
        $error = 'Elige un PIN menos obvio (por ejemplo, evita 1234 o 0000).';
      } else {
        $stmt = $conn->prepare('SELECT password FROM usuarios WHERE id = ?');
        $stmt->execute([$usuarioId]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($contrasenaActual, $hash)) {
          $error = 'Contrasena actual incorrecta.';
        } else {
          $pinHash = appHashRecoveryPin($pinNuevo);
          $upd = $conn->prepare('UPDATE usuarios SET recovery_pin_hash = ?, recovery_pin_set_at = NOW(), pin_failed_attempts = 0, pin_locked_until = NULL WHERE id = ?');
          $upd->execute([$pinHash, $usuarioId]);
          appFlash('success', 'Tu PIN de recuperacion fue actualizado. Guardalo en un lugar seguro.', 'PIN actualizado');
          appRedirect('perfil.php');
        }
      }
    }
  }
}

$pedidosStmt = $conn->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY fecha DESC");
$pedidosStmt->execute([$usuarioId]);
$pedidos = $pedidosStmt->fetchAll(PDO::FETCH_ASSOC);

$resenasStmt = $conn->prepare("
  SELECT r.*, p.nombre AS producto
  FROM `reseñas` r
  LEFT JOIN productos p ON r.producto_id = p.id
  WHERE r.usuario_id = ?
  ORDER BY r.fecha DESC
");
$resenasStmt->execute([$usuarioId]);
$resenas = $resenasStmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<main class="container py-5">
  <h1 class="text-center mb-5">Mi perfil</h1>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card mb-5 shadow-sm">
    <div class="card-body">
      <h3 class="card-title mb-4">Editar datos personales</h3>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('perfil_form')) ?>">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars((string) $usuario['nombre']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Correo electronico</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string) $usuario['email']) ?>" required>
        </div>
        <button type="submit" name="actualizar_datos" class="btn btn-primary">Guardar cambios</button>
      </form>
    </div>
  </div>

  <div class="card mb-5 shadow-sm">
    <div class="card-body">
      <h3 class="card-title mb-4">Cambiar contrasena</h3>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('perfil_password_form')) ?>">
        <div class="mb-3">
          <label class="form-label">Contrasena actual</label>
          <input type="password" name="contrasena_actual" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Nueva contrasena</label>
          <input type="password" name="nueva_contrasena" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirmar nueva contrasena</label>
          <input type="password" name="confirmar_contrasena" class="form-control" required>
        </div>
        <button type="submit" name="cambiar_contrasena" class="btn btn-warning">Actualizar contrasena</button>
      </form>
    </div>
  </div>

  <div class="card mb-5 shadow-sm">
    <div class="card-body">
      <h3 class="card-title mb-2">PIN de recuperacion</h3>
      <p class="text-soft mb-4">
        Estado: <strong><?= $pinDisponible ? ($pinConfigurado ? 'Configurado' : 'No configurado') : 'No disponible' ?></strong>
      </p>

      <?php if (!$pinDisponible): ?>
        <div class="alert alert-warning mb-0">
          El PIN de recuperacion no esta habilitado en tu base de datos. Aplica la migracion para activarlo.
        </div>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('perfil_pin_form')) ?>">

          <div class="mb-3">
            <label class="form-label">Contrasena actual</label>
            <input type="password" name="contrasena_actual_pin" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Nuevo PIN (4 digitos)</label>
            <input type="password" name="pin_recuperacion" class="form-control" required inputmode="numeric" pattern="\d{4}" maxlength="4">
            <div class="form-text text-soft">Este PIN se pedira cuando vayas a recuperar tu contrasena.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirmar PIN</label>
            <input type="password" name="pin_recuperacion_confirmar" class="form-control" required inputmode="numeric" pattern="\d{4}" maxlength="4">
          </div>

          <button type="submit" name="actualizar_pin" class="btn btn-outline-primary">Guardar PIN</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-5 shadow-sm">
    <div class="card-body">
      <h3 class="card-title mb-4">Historial de pedidos</h3>
      <?php if ($pedidos): ?>
        <div class="table-responsive">
          <table class="table table-bordered text-center align-middle" data-datatable="true" data-no-sort="5">
            <thead class="table-light">
              <tr>
                <th>Fecha</th>
                <th>Subtotal</th>
                <th>Envio</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Ver</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pedidos as $p): ?>
                <?php $estadoKey = normalizarTextoPedido((string) $p['estado']); ?>
                <tr>
                  <td><?= htmlspecialchars((string) $p['fecha']) ?></td>
                  <td>$<?= number_format((float) ($p['subtotal_productos'] ?? 0), 0, ',', '.') ?></td>
                  <td>$<?= number_format((float) ($p['costo_envio'] ?? 0), 0, ',', '.') ?></td>
                  <td>$<?= number_format((float) $p['total'], 0, ',', '.') ?></td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($etiquetasEstado[$estadoKey] ?? ucfirst($estadoKey)) ?></span></td>
                  <td><a href="ver_pedido.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline-primary btn-sm">Ver</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="alert alert-info">No has realizado pedidos aun.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-5 shadow-sm">
    <div class="card-body">
      <h3 class="card-title mb-4">Mis resenas</h3>
      <?php if ($resenas): ?>
        <?php foreach ($resenas as $res): ?>
          <div class="border-start border-4 border-primary bg-light p-3 rounded mb-3">
            <p class="mb-1">
              <strong><?= htmlspecialchars((string) ($res['producto'] ?? 'Producto retirado')) ?></strong>
              - Puntuacion: <?= (int) $res['puntuacion'] ?>/5
              <?php if ((int) ($res['compra_verificada'] ?? 0) === 1): ?>
                <span class="badge text-bg-success ms-2">Compra verificada</span>
              <?php endif; ?>
            </p>
            <p class="mb-1"><?= htmlspecialchars((string) $res['comentario']) ?></p>
            <small class="text-muted"><?= htmlspecialchars((string) $res['fecha']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="alert alert-secondary">No has dejado resenas todavia.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm mb-5">
    <div class="card-body">
      <h3 class="card-title mb-4">Mis favoritos</h3>
      <div id="contenedor-favoritos" class="row g-4"></div>
      <p class="mt-3 text-end"><a href="favoritos.php" class="btn btn-outline-danger">Ver todos en lista de deseos</a></p>
    </div>
  </div>
</main>

<?php include 'footer.php'; ?>
