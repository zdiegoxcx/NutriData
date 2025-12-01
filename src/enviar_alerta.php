<?php
// src/enviar_alerta.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar librerías manualmente
require_once __DIR__ . '/libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/config/correo.php'; // Tus credenciales

function notificarRiesgoDAEM($pdo, $datos_estudiante, $diagnostico, $imc, $peso, $altura) {
    
    // 1. Buscar el correo del Administrador DAEM en la BD
    // Buscamos al usuario con rol 'administradorDAEM'
    $sql = "SELECT Email FROM Usuario u 
            JOIN Rol r ON u.Id_Rol = r.Id 
            WHERE r.Nombre = 'administradorDAEM' AND u.Estado = 1 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || empty($admin['Email'])) {
        return "No se encontró email del Director DAEM.";
    }
    $email_destino = $admin['Email'];

    // 2. Configurar el Correo
    $mail = new PHPMailer(true);

    try {
        // Servidor
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = MAIL_PORT;

        // Cabeceras
        $mail->setFrom(MAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($email_destino); // Director DAEM

        // Contenido
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "⚠️ ALERTA NUTRICIONAL: " . $datos_estudiante['Nombres'];

        $cuerpo = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; max-width: 600px;'>
            <h2 style='color: #dc3545;'>Detección de Riesgo Nutricional</h2>
            <p>Estimado Administrador DAEM,</p>
            <p>El sistema NutriData ha detectado un caso que requiere atención inmediata.</p>
            
            <hr>
            <h3>Detalles del Estudiante</h3>
            <ul>
                <li><strong>Nombre:</strong> {$datos_estudiante['Nombres']} {$datos_estudiante['ApellidoPaterno']}</li>
                <li><strong>RUT:</strong> {$datos_estudiante['Rut']}</li>
                <li><strong>Diagnóstico:</strong> <span style='color:red; font-weight:bold;'>$diagnostico</span></li>
                <li><strong>IMC:</strong> $imc</li>
                <li><strong>Medición:</strong> Peso $peso kg / Altura $altura m</li>
            </ul>
            <hr>
            
            <p style='color: #666; font-size: 12px;'>Este es un mensaje automático. Por favor ingrese al Dashboard para gestionar el caso.</p>
        </div>
        ";

        $mail->Body = $cuerpo;
        $mail->AltBody = "Alerta de Riesgo: Estudiante {$datos_estudiante['Rut']} presenta $diagnostico (IMC: $imc).";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return "Error al enviar correo: {$mail->ErrorInfo}";
    }
}
?>