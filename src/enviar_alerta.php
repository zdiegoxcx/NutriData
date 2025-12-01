<?php
// src/enviar_alerta.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/config/correo.php'; 

// AHORA RECIBE EL ID DE LA ALERTA PARA EL LINK
function notificarRiesgoDAEM($pdo, $datos, $diagnostico, $imc, $peso, $altura, $id_alerta) {
    
    // 1. Buscar correo DAEM
    $stmt = $pdo->prepare("SELECT Email FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id WHERE r.Nombre = 'administradorDAEM' AND u.Estado = 1 LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || empty($admin['Email'])) return "No se encontró email DAEM.";
    
    // 2. Generar Link Dinámico (Detecta localhost)
    $host = $_SERVER['HTTP_HOST']; 
    $protocolo = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    // Ajusta 'NutriData' si tu carpeta se llama distinto
    $link = "$protocolo://$host/NutriData/public/AdminDAEM/gestionar_alerta.php?id=$id_alerta";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($admin['Email']); 

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "🚨 Alerta: {$datos['Nombres']} ({$datos['NombreEstablecimiento']})";

        $cuerpo = "
        <div style='font-family:Segoe UI, sans-serif; color:#333; max-width:600px; border:1px solid #e0e0e0; border-radius:8px; overflow:hidden;'>
            <div style='background-color:#dc3545; padding:15px; text-align:center;'>
                <h2 style='color:white; margin:0;'>Riesgo Nutricional Detectado</h2>
            </div>
            <div style='padding:20px;'>
                <p>El sistema ha detectado una medición crítica que requiere su atención.</p>
                
                <table style='width:100%; border-collapse:collapse; margin-top:15px; border:1px solid #eee;'>
                    <tr style='background:#f9f9f9;'><td style='padding:8px; font-weight:bold;'>Estudiante:</td><td style='padding:8px;'>{$datos['Nombres']} {$datos['ApellidoPaterno']}</td></tr>
                    <tr><td style='padding:8px; font-weight:bold;'>RUT:</td><td style='padding:8px;'>{$datos['Rut']}</td></tr>
                    <tr style='background:#f9f9f9;'><td style='padding:8px; font-weight:bold;'>Curso:</td><td style='padding:8px;'>{$datos['NombreCurso']}</td></tr>
                    <tr><td style='padding:8px; font-weight:bold;'>Colegio:</td><td style='padding:8px;'>{$datos['NombreEstablecimiento']}</td></tr>
                    <tr style='background:#fff0f0;'><td style='padding:8px; font-weight:bold; color:#dc3545;'>Diagnóstico:</td><td style='padding:8px; color:#dc3545; font-weight:bold;'>$diagnostico</td></tr>
                    <tr><td style='padding:8px; font-weight:bold;'>IMC:</td><td style='padding:8px;'>$imc</td></tr>
                </table>

                <div style='text-align:center; margin-top:25px; margin-bottom:15px;'>
                    <a href='$link' style='background-color:#0d6efd; color:white; padding:12px 24px; text-decoration:none; border-radius:5px; font-weight:bold;'>Gestionar Caso Ahora</a>
                </div>
                <hr>
                <p style='text-align:center; font-size:11px; color:#999;'>
                    Mensaje automático de NutriData.<br>
                    Si el botón no funciona: $link
                </p>
            </div>
        </div>
        ";

        $mail->Body = $cuerpo;
        $mail->send();
        return true;

    } catch (Exception $e) {
        return "Error Mail: {$mail->ErrorInfo}";
    }
}
?>