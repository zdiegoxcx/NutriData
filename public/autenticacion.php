<?php
session_start();

// --- ¡RUTA CORREGIDA! ---
require_once __DIR__ . '/../src/config/db.php'; 

// 1. Obtener la conexión PDO
$pdo = getConnection();

// 2. Verificar los campos del formulario (de login.html)
if (!isset($_POST['username'], $_POST['password'])) {
    header('Location: login.html');
    exit;
}

try {
    // 3. Preparamos la consulta (buscamos por RUT)
    // Agregamos 'Estado = 1' para asegurar que el usuario esté activo
    $stmt = $pdo->prepare('SELECT Id, Contraseña, Nombre, Apellido FROM Usuario WHERE Rut = ? AND Estado = 1');
    
    // 4. Ejecutamos la consulta
    $stmt->execute([$_POST['username']]); // 'username' es el Rut

    // 5. Obtenemos el usuario
    $user = $stmt->fetch();

    // 6. Verificamos al usuario Y la contraseña (¡SIN HASHEAR!)
    // Comparamos directamente el texto plano
    if ($user && $_POST['password'] === $user['Contraseña']) {
        
        // ¡Contraseña correcta! Creamos la sesión.
        session_regenerate_id();
        $_SESSION['loggedin'] = TRUE;
        $_SESSION['id'] = $user['Id'];
        $_SESSION['Nombre'] = $user['Nombre']; // Guardamos el Nombre para saludar
        
        header('Location: inicio.php');
        exit;

    } else {
        // Datos incorrectos (RUT no existe, clave incorrecta, o usuario inactivo)
        header('Location: login.html');
        exit;
    }

} catch (PDOException $e) {
    // Manejar error de base de datos
    echo "Error en la autenticación: " . $e->getMessage();
}
?>