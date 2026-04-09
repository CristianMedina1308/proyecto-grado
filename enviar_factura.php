<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

function enviarFacturaPorCorreo($correoDestino, $nombreUsuario, $archivoFacturaPDF, $nombreAdjunto)
{
  $mail = new PHPMailer(true);

  try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mecristian14@gmail.com';
    $mail->Password = 'xxxx';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('mecristian14@gmail.com', 'Tauro Store - Moda Masculina');
    $mail->addAddress($correoDestino, $nombreUsuario);

    $mail->isHTML(true);
    $mail->Subject = 'Factura de tu pedido - Tauro Store';
    $mail->Body = "Hola <strong>$nombreUsuario</strong>,<br><br>Gracias por tu compra. Adjuntamos la factura en PDF.<br><br>Esperamos verte pronto en Tauro Store.";

    $mail->addAttachment($archivoFacturaPDF, $nombreAdjunto);
    $mail->send();
    return true;
  } catch (Exception $e) {
    return false;
  }
}
