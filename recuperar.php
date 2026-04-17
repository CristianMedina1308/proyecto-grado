<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/conexion.php';

$mensaje = '';
$pinDisponible = appDbHasColumn($conn, 'usuarios', 'recovery_pin_hash');
$hasAttemptsCol = appDbHasColumn($conn, 'usuarios', 'pin_failed_attempts');
$hasLockedCol = appDbHasColumn($conn, 'usuarios', 'pin_locked_until');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!appValidarCsrf('recuperar_form', $_POST['csrf_token'] ?? null)) {
    $mensaje = "<div class='alert alert-warning'>⚠️ La sesion del formulario expiro. Intenta nuevamente.</div>";
  } elseif (!$pinDisponible) {
    $mensaje = "<div class='alert alert-warning'>⚠️ El PIN de recuperacion aun no esta habilitado en la base de datos.</div>";
  } else {
    $identificador = trim((string) ($_POST['identificador'] ?? ''));
    $pin = trim((string) ($_POST['pin'] ?? ''));

    $isEmail = filter_var($identificador, FILTER_VALIDATE_EMAIL) !== false;
    $isPhone = preg_match('/^[0-9+ ]{7,20}$/', $identificador) === 1;

    if (!$isEmail && !$isPhone) {
      $mensaje = "<div class='alert alert-warning'>⚠️ Ingresa un correo o telefono valido.</div>";
    } elseif (!appValidateRecoveryPin($pin)) {
      $mensaje = "<div class='alert alert-warning'>⚠️ El PIN debe tener 4 digitos.</div>";
    } else {
      $cols = ['id', 'nombre', 'email', 'telefono', 'recovery_pin_hash'];
      if ($hasAttemptsCol) {
        $cols[] = 'pin_failed_attempts';
      }
      if ($hasLockedCol) {
        $cols[] = 'pin_locked_until';
      }

      $sql = 'SELECT ' . implode(', ', $cols) . ' FROM usuarios WHERE ' . ($isEmail ? 'email' : 'telefono') . ' = ? LIMIT 1';
      $stmt = $conn->prepare($sql);
      $stmt->execute([$identificador]);
      $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$usuario) {
        $mensaje = "<div class='alert alert-danger'>❌ No fue posible validar los datos. Verifica e intenta de nuevo.</div>";
      } elseif (trim((string) ($usuario['recovery_pin_hash'] ?? '')) === '') {
        $mensaje = "<div class='alert alert-warning'>⚠️ Esta cuenta no tiene PIN de recuperacion configurado. Si aun puedes entrar, configuralo en tu perfil.</div>";
      } else {
        $lockedUntil = trim((string) ($usuario['pin_locked_until'] ?? ''));
        if ($lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time()) {
          $mensaje = "<div class='alert alert-warning'>⚠️ Demasiados intentos. Espera un momento e intentalo de nuevo.</div>";
        } elseif (!appVerifyRecoveryPin($pin, (string) $usuario['recovery_pin_hash'])) {
          // Fallo de PIN: sumar intento y bloquear si es necesario.
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

          $mensaje = "<div class='alert alert-danger'>❌ No fue posible validar los datos. Verifica e intenta de nuevo.</div>";
        } else {
          // OK: generar token y redirigir a restablecer.
          $token = bin2hex(random_bytes(32));
          $upd = $conn->prepare('UPDATE usuarios SET token_recuperacion = ?, pin_failed_attempts = 0, pin_locked_until = NULL WHERE id = ?');
          try {
            $upd->execute([$token, (int) $usuario['id']]);
          } catch (Throwable $e) {
            // Si las columnas de control no existen, al menos guarda el token.
            $conn->prepare('UPDATE usuarios SET token_recuperacion = ? WHERE id = ?')->execute([$token, (int) $usuario['id']]);
          }

          appRedirect('restablecer.php?token=' . rawurlencode($token));
        }
      }
    }
  }
}

include 'header.php';
?>

<div class="container py-5">
  <h1 class="text-center mb-4">🔑 Recuperar Contraseña</h1>

  <?php if ($mensaje): ?>
    <div class="mb-4"><?= $mensaje ?></div>
  <?php endif; ?>

  <form method="post" class="mx-auto" style="max-width:400px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('recuperar_form')) ?>">
    <div class="mb-3">
      <label class="form-label">Correo o telefono registrado</label>
      <input type="text" name="identificador" class="form-control" required placeholder="Ej: correo@dominio.com o 3001234567" value="<?= htmlspecialchars((string) ($_POST['identificador'] ?? '')) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">PIN de recuperacion (4 digitos)</label>
      <input type="password" name="pin" class="form-control" required inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="Ej: 4827">
    </div>

    <button type="submit" class="btn btn-primary w-100">Continuar</button>
  </form>
</div>

<?php include 'footer.php'; ?>
