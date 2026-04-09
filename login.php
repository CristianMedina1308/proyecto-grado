<?php
include 'includes/conexion.php';
require_once 'includes/app.php';

$mensajeError = '';
$mensajeWarning = '';

if (isset($_POST['login'])) {
  if (!appValidarCsrf('login_form', $_POST['csrf_token'] ?? null)) {
    $mensajeError = 'La sesion del formulario expiro. Intenta nuevamente.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $mensajeWarning = 'Ingresa un correo electronico valido.';
    } else {
      $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
      $stmt->execute([$email]);
      $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($usuario && password_verify($password, $usuario['password'])) {
        appLoginUsuario($usuario);
        appFlash('success', 'Has iniciado sesion correctamente.', 'Bienvenido');
        $redirect = ($usuario['rol'] === 'admin') ? 'admin/index.php' : 'index.php';
        appRedirect($redirect);
      }

      $mensajeError = 'Correo o contrasena incorrectos.';
    }
  }
}

include 'header.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card p-5">
        <h2 class="text-center mb-4 checkout-title">Iniciar sesion</h2>

        <?php if ($mensajeError !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($mensajeError) ?></div>
        <?php endif; ?>
        <?php if ($mensajeWarning !== ''): ?>
          <div class="alert alert-warning"><?= htmlspecialchars($mensajeWarning) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('login_form')) ?>">
          <div class="mb-4">
            <label class="form-label">Correo electronico</label>
            <input type="email" name="email" class="form-control" required>
          </div>

          <div class="mb-4">
            <label class="form-label">Contrasena</label>
            <input type="password" name="password" class="form-control" required>
          </div>

          <button type="submit" name="login" class="btn btn-primary w-100">Ingresar</button>

          <div class="text-center mt-4 text-soft">
            No tienes cuenta?
            <a href="registro.php">Registrate aqui</a>
            <br>
            <a href="recuperar.php" style="font-size:0.92rem;">Olvidaste tu contrasena?</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
