<?php
include 'includes/conexion.php';
require_once 'includes/app.php';

$mensajeError = '';
$aceptaTerminos = isset($_POST['acepta_terminos']);

if (isset($_POST['registro'])) {
  if (!appValidarCsrf('registro_form', $_POST['csrf_token'] ?? null)) {
    $mensajeError = 'La sesion del formulario expiro. Intenta nuevamente.';
  } else {
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passwordPlano = (string) ($_POST['password'] ?? '');

    if ($nombre === '' || $telefono === '' || $email === '' || $passwordPlano === '') {
      $mensajeError = 'Todos los campos son obligatorios.';
    } elseif (!$aceptaTerminos) {
      $mensajeError = 'Debes aceptar los terminos y condiciones para registrarte.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $mensajeError = 'Ingresa un correo electronico valido.';
    } elseif (!preg_match('/^[0-9+ ]{7,20}$/', $telefono)) {
      $mensajeError = 'Ingresa un telefono valido.';
    } else {
      $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
      $stmt->execute([$email]);

      if ($stmt->rowCount() > 0) {
        $mensajeError = 'Ese correo ya esta registrado.';
      } else {
        $passwordHash = password_hash($passwordPlano, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, telefono, email, password, rol) VALUES (?, ?, ?, ?, 'cliente')");
        $stmt->execute([$nombre, $telefono, $email, $passwordHash]);
        appFlash('success', 'Tu cuenta fue creada correctamente. Ya puedes iniciar sesion.', 'Registro exitoso');
        appRedirect('login.php');
      }
    }
  }
}

include 'header.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card p-5">
        <h2 class="text-center mb-4 checkout-title">Crear cuenta</h2>

        <?php if ($mensajeError !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($mensajeError) ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('registro_form')) ?>">
          <div class="mb-4">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
          </div>

          <div class="mb-4">
            <label class="form-label">Numero de telefono</label>
            <input type="text"
                   name="telefono"
                   class="form-control"
                   required
                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                   pattern="[0-9+ ]{7,20}"
                   title="Ingresa un numero de telefono valido">
          </div>

          <div class="mb-4">
            <label class="form-label">Correo electronico</label>
            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>

          <div class="mb-4">
            <label class="form-label">Contrasena</label>
            <input type="password" name="password" class="form-control" required>
          </div>

           <div class="form-check mb-4">
             <input class="form-check-input" type="checkbox" value="1" id="acepta_terminos_registro" name="acepta_terminos" <?= $aceptaTerminos ? 'checked' : '' ?>>
             <label class="form-check-label text-soft" for="acepta_terminos_registro">
               He leido y acepto los <a href="#" onclick="mostrarTerminosModal('acepta_terminos_registro'); return false;">terminos y condiciones</a>.
             </label>
           </div>

          <button type="submit" name="registro" class="btn btn-primary w-100">Registrarse</button>

          <div class="text-center mt-4 text-soft">
            Ya tienes cuenta?
            <a href="login.php">Inicia sesion</a>
          </div>
         </form>
       </div>
     </div>
   </div>
 </div>

<script>
// La validación se maneja en assets/js/terminos.js
document.addEventListener("DOMContentLoaded", function() {
  const checkboxTerminos = document.getElementById("acepta_terminos_registro");
  const btnRegistro = document.querySelector("button[name='registro']");

  if (checkboxTerminos && btnRegistro) {
    // Actualizar estado visual del botón según checkbox
    checkboxTerminos.addEventListener("change", function() {
      console.log("Términos:", this.checked ? "aceptados" : "no aceptados");
    });

    btnRegistro.disabled = false;
  }
});
</script>

 <?php include 'footer.php'; ?>
