<?php
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';
require 'includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mecristian14@gmail.com';
    $mail->Password = 'xxxxxxxxx';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('mecristian14@gmail.com', 'Tauro Store - Moda Masculina');
    $mail->addAddress('mecristian14@gmail.com', 'Cliente de prueba');

    $mail->isHTML(true);
    $mail->Subject = 'Prueba de correo - Tauro Store';
    $mail->Body = '<h3>Hola, este es un mensaje de prueba.</h3><p>Si lo ves, el correo funciona.</p>';

    $mail->send();
    echo 'Correo enviado exitosamente.';
} catch (Exception $e) {
    echo 'Error al enviar el correo: ' . $mail->ErrorInfo;
}
