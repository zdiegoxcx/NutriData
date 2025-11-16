<?php
session_start();
// --- GUARDIÁNN DE LA PÁGINA ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['user_rol'] != 'profesor') {
    header("Location: login.php");
    exit;
}
// --- FIN DEL GUARDIÁNN ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Profesor</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Bienvenido, Profesor <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></h1>
        <p>Este es tu panel de <strong>Profesor</strong>.</p>
        <p>Aquí puedes gestionar tus cursos y registrar datos nutricionales de tus estudiantes.</p>
        <br>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
</body>
</html>