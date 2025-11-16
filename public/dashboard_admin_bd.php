<?php
session_start();

// --- GUARDIÁN DE LA PÁGINA ---
// 1. Verificar si hay sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// 2. Verificar si tiene el ROL correcto
if ($_SESSION['user_rol'] != 'administradorBD') {
    // Si no es su rol, lo echamos (o a una pág. de "acceso denegado")
    header("Location: login.php");
    exit;
}
// --- FIN DEL GUARDIÁN ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin BD</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></h1>
        <p>Este es el panel del <strong>Administrador de Base de Datos</strong>.</p>
        <p>Aquí puedes gestionar las tablas y la estructura del sistema.</p>
        <br>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
</body>
</html>