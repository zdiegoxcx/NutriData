<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - NutriData</title>
    <!-- CSS con versión dinámica para evitar caché -->
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">

    <!-- Lado Izquierdo (Banner) -->
    <div class="login-banner">
        <div class="banner-content">
            <div style="font-size: 4rem; margin-bottom: 20px;">
                <i class="fa-solid fa-apple-whole"></i>
            </div>
            <h1>NutriData</h1>
            <p>Sistema integral para el monitoreo y gestión nutricional escolar de la Región del Biobío.</p>
        </div>
    </div>

    <!-- Lado Derecho (Formulario) -->
    <div class="login-form-container">
        <div class="login-wrapper">
            
            <!-- Encabezado (Visible solo en escritorio aquí) -->
            <div class="login-header-desktop">
                <h2 class="login-title">Bienvenido de nuevo</h2>
                <p class="login-subtitle">Ingresa tus credenciales para acceder al sistema.</p>
            </div>

            <!-- Encabezado Móvil (Visible solo en celular) -->
            <div class="login-header-mobile">
                <h1>NutriData</h1>
                <p>Acceso al Sistema</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mensaje error" style="margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form action="validar.php" method="POST">
                
                <div class="input-group">
                    <label for="rut">RUT de Usuario</label>
                    <div class="input-wrapper">
                        <input type="text" id="rut" name="rut" placeholder="Ej: 12345678-9" required autofocus>
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label for="contrasena">Contraseña</label>
                    <div class="input-wrapper">
                        <input type="password" id="contrasena" name="contrasena" placeholder="Ingresa tu contraseña" required>
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login-clean">
                    Iniciar Sesión
                </button>

            </form>

            <div class="login-footer">
                &copy; <?php echo date("Y"); ?> DAEM NutriMonitor. Todos los derechos reservados.
            </div>
        </div>
    </div>

</body>
</html>