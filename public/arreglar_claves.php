<?php
// arreglar_claves.php
// Este script sobrescribe TODAS las contraseñas de la base de datos con "12345" encriptado correctamente.

require_once __DIR__ . '/../src/config/db.php';

try {
    $pdo = getConnection();
    
    // 1. Generamos el hash fresco y correcto de "12345" usando el PHP de tu propio servidor
    $password_texto = '12345';
    $nuevo_hash = password_hash($password_texto, PASSWORD_DEFAULT);
    
    // 2. Actualizamos TODOS los usuarios
    $sql = "UPDATE Usuario SET Contraseña = :hash";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hash' => $nuevo_hash]);
    
    echo "<h1>✅ ¡Reparación Exitosa!</h1>";
    echo "<p>Se han actualizado todas las contraseñas de la base de datos.</p>";
    echo "<p>Nueva contraseña para todos: <strong>12345</strong></p>";
    echo "<p>Hash generado y guardado: <small>$nuevo_hash</small></p>";
    echo "<br>";
    echo "<a href='login.php'>👉 Ir a Iniciar Sesión</a>";

} catch (PDOException $e) {
    echo "<h1>❌ Error Grave</h1>";
    echo "No se pudo conectar o actualizar la BD: " . $e->getMessage();
}
?>