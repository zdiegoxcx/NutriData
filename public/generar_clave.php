<?php
// Cambia este texto por la contraseña que quieras encriptar
$password_texto_plano = "12345"; 

// Generar el hash seguro
$hash = password_hash($password_texto_plano, PASSWORD_DEFAULT);

echo "<h1>Generador de Hash</h1>";
echo "<p>Contraseña: <strong>" . $password_texto_plano . "</strong></p>";
echo "<p>Hash para la Base de Datos:</p>";
echo "<textarea cols='70' rows='3'>" . $hash . "</textarea>";
?>