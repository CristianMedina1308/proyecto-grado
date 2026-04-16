<?php
include 'includes/conexion.php';
session_start();
include 'header.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $telefono = trim($_POST['telefono'] ?? '');

  if (preg_match('/^[0-9+]{7,15}$/', $telefono)) {
    // Buscar usuario por teléfono
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE telefono = ?");
    $stmt->execute([$telefono]);

    if ($stmt->rowCount() > 0) {
      $codigo = random_int(100000, 999999);

      // Guardar en sesión
      $_SESSION['telefono_recuperacion'] = $telefono;
      $_SESSION['codigo_recuperacion'] = $codigo;
      $_SESSION['recuperacion_telefono'] = $telefono;
      $_SESSION['recuperacion_codigo'] = $codigo;

      // Aquí simularíamos que se envía el código por WhatsApp
      $mensaje = "
        <div class='alert alert-success'>
          ✅ Código de verificación generado: <strong>$codigo</strong>.<br><br>
          Por favor envía este código por WhatsApp a <a href='https://wa.me/573175378274' target='_blank' rel='noopener'>+57 317 537 8274</a> para confirmar tu identidad.<br><br>
          <small>(Simulación para pruebas locales)</small>
        </div>
        <div class='text-center mt-4'>
          <a href='verificar_codigo.php' class='btn btn-success'>Ingresar Código</a>
        </div>
      ";
    } else {
      $mensaje = "<div class='alert alert-danger'>❌ No encontramos un usuario con ese número de teléfono.</div>";
    }
  } else {
    $mensaje = "<div class='alert alert-warning'>⚠️ Por favor ingresa un número de teléfono válido.</div>";
  }
}
?>

<div class="container py-5">
  <h1 class="text-center mb-4">🔑 Recuperar Contraseña</h1>

  <?php if ($mensaje): ?>
    <div class="mb-4"><?= $mensaje ?></div>
  <?php endif; ?>

  <form method="post" class="mx-auto" style="max-width:400px;">
    <div class="mb-3">
      <label class="form-label">Número de teléfono registrado</label>
      <input type="text" name="telefono" class="form-control" required placeholder="Ej: 3001234567">
    </div>
    <button type="submit" class="btn btn-primary w-100">Enviar Código</button>
  </form>
</div>

<?php include 'footer.php'; ?>
