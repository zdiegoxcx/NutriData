<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - NutriData</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">

    <div class="login-banner">
        <div class="banner-content">
            <h1>NutriData</h1>
            <p>Sistema integral para el monitoreo y gestión nutricional escolar de la Región del Biobío.</p>
        </div>
    </div>

    <div class="login-form-container">
        <div class="login-wrapper">
            
            <div class="login-header-desktop">
                <h2 class="login-title">Bienvenido de nuevo</h2>
                <p class="login-subtitle">Ingresa tus credenciales para acceder al sistema.</p>
            </div>

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
                        <input type="text" id="rut" name="rut" 
                               placeholder="Ej: 12.345.678-9" 
                               required autofocus 
                               maxlength="12"
                               oninput="darFormatoRut(this)">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label for="contrasena">Contraseña</label>
                    <div class="input-wrapper" style="position: relative;">
                        <input type="password" id="contrasena" name="contrasena" 
                               placeholder="Ingresa tu contraseña" 
                               required 
                               maxlength="50"
                               style="padding-right: 40px;">
                        
                        <i class="fa-solid fa-lock"></i>

                        <i class="fa-solid fa-eye" id="togglePassword" 
                           style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #9ca3af; left: auto;"></i>
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

    <script src="js/formato_rut.js"></script>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#contrasena');

        togglePassword.addEventListener('click', function (e) {
            // Alternar el atributo type
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Alternar el icono del ojo (abierto/tachado)
            this.classList.toggle('fa-eye-slash');
        });
    </script>

</body>
</html>