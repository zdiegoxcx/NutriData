<?php
session_start();
// --- GUARDIÁN DE LA PÁGINA ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: login.php");
    exit;
}
// --- FIN DEL GUARDIÁN ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin DAEM</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></h1>
        <p>Este es el panel del <strong>Administrador DAEM</strong>.</p>
        <p>Aquí puedes ver reportes generales y administrar establecimientos.</p>
        <br>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
</body>
</html>