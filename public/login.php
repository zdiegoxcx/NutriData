<?php
// Iniciar sesión para poder manejar mensajes de error
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - NutriData</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="login-container">
        <h1>Iniciar Sesión</h1>

        <?php
        // Mostrar mensajes de error si existen
        if (isset($_SESSION['error'])) {
            echo '<div class="mensaje error">' . $_SESSION['error'] . '</div>';
            // Limpiar el error para que no se muestre de nuevo
            unset($_SESSION['error']);
        }
        ?>

        <form action="validar.php" method="POST">
            <div class="form-group">
                <label for="rut">RUT (sin puntos y con guión)</label>
                <input type="text" id="rut" name="rut" placeholder="Ej: 12345678-9" required>
            </div>
            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </div>
            <button type="submit" class="btn-login">Ingresar</button>
        </form>
    </div>
</body>
</html>