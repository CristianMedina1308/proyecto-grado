<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/conexion.php';

// Best effort: si faltan columnas del PIN, intentamos crearlas (si hay permisos).
appEnsureRecoveryPinSchema($conn);

$token = trim((string) ($_GET['token'] ?? ($_POST['token'] ?? '')));
$mensaje = '';
$mostrarFormulario = false;
$requirePin = false;

$pinDisponible = appDbHasColumn($conn, 'usuarios', 'recovery_pin_hash');
$hasAttemptsCol = appDbHasColumn($conn, 'usuarios', 'pin_failed_attempts');
$hasLockedCol = appDbHasColumn($conn, 'usuarios', 'pin_locked_until');

$fetchUserByToken = static function (PDO $conn, string $token, bool $pinDisponible, bool $hasAttemptsCol, bool $hasLockedCol): ?array {
  if ($token === '') {
    return null;
  }

  $cols = ['id', 'token_recuperacion'];
  if ($pinDisponible) {
    $cols[] = 'recovery_pin_hash';
  }
  if ($hasAttemptsCol) {
    $cols[] = 'pin_failed_attempts';
  }
  if ($hasLockedCol) {
    $cols[] = 'pin_locked_until';
  }

  $stmt = $conn->prepare('SELECT ' . implode(', ', $cols) . ' FROM usuarios WHERE token_recuperacion = ? LIMIT 1');
  $stmt->execute([$token]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
};

if ($token === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  $mensaje = "❗ Este enlace no es válido. Solicita uno nuevo desde <a href='recuperar.php'>recuperar contraseña</a>.";
} elseif ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token) !== 1) {
  $mensaje = "❌ Token inválido.";
} else {
  $usuario = $fetchUserByToken($conn, $token, $pinDisponible, $hasAttemptsCol, $hasLockedCol);
  if ($token !== '' && !$usuario) {
    $mensaje = "❌ Token inválido o expirado.";
  } else {
    $requirePin = $pinDisponible && trim((string) ($usuario['recovery_pin_hash'] ?? '')) !== '';
    $mostrarFormulario = $token !== '';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!appValidarCsrf('restablecer_form', $_POST['csrf_token'] ?? null)) {
    $mensaje = 'La sesion del formulario expiro. Intenta nuevamente.';
  } else {
    $nueva = (string) ($_POST['nueva'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');
    $pinIngresado = trim((string) ($_POST['pin'] ?? ''));

    if ($nueva !== $confirmar || strlen($nueva) < 6) {
      $mensaje = '❗ Las contraseñas no coinciden o son demasiado cortas.';
    } else {
      $usuario = $fetchUserByToken($conn, $token, $pinDisponible, $hasAttemptsCol, $hasLockedCol);
      if (!$usuario) {
        $mensaje = '❌ Token inválido o expirado.';
      } else {
        $requirePin = $pinDisponible && trim((string) ($usuario['recovery_pin_hash'] ?? '')) !== '';

        if ($requirePin) {
          $lockedUntil = trim((string) ($usuario['pin_locked_until'] ?? ''));
          if ($lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time()) {
            $mensaje = '⚠️ Demasiados intentos. Espera un momento e intentalo de nuevo.';
          } elseif (!appValidateRecoveryPin($pinIngresado)) {
            $mensaje = '⚠️ Ingresa tu PIN de 4 digitos.';
          } elseif (!appVerifyRecoveryPin($pinIngresado, (string) $usuario['recovery_pin_hash'])) {
            if ($hasAttemptsCol || $hasLockedCol) {
              $intentos = (int) ($usuario['pin_failed_attempts'] ?? 0);
              $intentos++;

              $setLocked = false;
              $lockedValue = null;
              if ($intentos >= 5 && $hasLockedCol) {
                $setLocked = true;
                $lockedValue = date('Y-m-d H:i:s', time() + 15 * 60);
              }

              $parts = [];
              $params = [];
              if ($hasAttemptsCol) {
                $parts[] = 'pin_failed_attempts = ?';
                $params[] = $intentos;
              }
              if ($hasLockedCol && $setLocked) {
                $parts[] = 'pin_locked_until = ?';
                $params[] = $lockedValue;
              }

              if ($parts) {
                $params[] = (int) $usuario['id'];
                $conn->prepare('UPDATE usuarios SET ' . implode(', ', $parts) . ' WHERE id = ?')->execute($params);
              }
            }

            $mensaje = '❌ PIN incorrecto.';
          }
        }

        if ($mensaje === '') {
          $hash = password_hash($nueva, PASSWORD_DEFAULT);
          try {
            $conn->prepare('UPDATE usuarios SET password = ?, token_recuperacion = NULL, pin_failed_attempts = 0, pin_locked_until = NULL WHERE id = ?')
              ->execute([$hash, (int) $usuario['id']]);
          } catch (Throwable $e) {
            $conn->prepare('UPDATE usuarios SET password = ?, token_recuperacion = NULL WHERE id = ?')
              ->execute([$hash, (int) $usuario['id']]);
          }

          $mensaje = "✅ Contraseña actualizada. <a href='login.php'>Inicia sesión</a>";
          $mostrarFormulario = false;
        }
      }
    }
  }
}

include 'header.php';
?>

<div class="container py-5">
  <h1 class="text-center mb-4">🔒 Restablecer Contraseña</h1>
  <?php if ($mensaje): ?>
    <div class="alert alert-info"><?= $mensaje ?></div>
  <?php endif; ?>
  <?php if ($mostrarFormulario && $token): ?>
    <form method="post" class="mx-auto" style="max-width: 400px;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('restablecer_form')) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <?php if ($requirePin): ?>
        <div class="mb-3">
          <label class="form-label">PIN de recuperacion</label>
          <input type="password" name="pin" class="form-control" required inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="4 digitos">
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Nueva contraseña</label>
        <input type="password" name="nueva" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirmar contraseña</label>
        <input type="password" name="confirmar" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-success w-100">Cambiar contraseña</button>
    </form>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
