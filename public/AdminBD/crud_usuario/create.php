<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
require_once __DIR__ . '/../../../src/config/validaciones.php';
$pdo = getConnection();

// --- GUARDIÁN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$errores = [];
$rut = $nombre = $apellido = $email = $telefono = $contrasena = $rol_id = '';

// Obtener roles para el select
$roles = $pdo->query("SELECT Id, Nombre FROM Rol ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $contrasena = $_POST['contrasena'];
    $rol_id = $_POST['rol_id'];
    $estado = 1; 

    // Validaciones
    if (!validarRut($rut)) $errores[] = "El RUT ingresado no es válido.";
    if (!validarSoloLetras($nombre)) $errores[] = "El nombre solo puede contener letras.";
    if (!validarSoloLetras($apellido)) $errores[] = "El apellido solo puede contener letras.";
    if (empty($rut) || empty($nombre) || empty($apellido) || empty($contrasena) || empty($rol_id)) {
        $errores[] = "Complete los campos obligatorios.";
    }

    // Validar duplicado
    if (empty($errores)) {
        $stmt_check = $pdo->prepare("SELECT Id FROM Usuario WHERE Rut = ?");
        $stmt_check->execute([$rut]);
        if ($stmt_check->rowCount() > 0) $errores[] = "El RUT ya está registrado.";
    }

    if (empty($errores)) {
        try {
            $pass_hashed = password_hash($contrasena, PASSWORD_DEFAULT);
            $sql = "INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$rol_id, $rut, $nombre, $apellido, $pass_hashed, $telefono, $email, $estado]);

            $_SESSION['success_message'] = "Usuario creado exitosamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error al crear usuario: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Usuario</title>
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="../../dashboard_admin_bd.php?vista=usuarios" class="btn-header-back">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">Admin BD</div>
        </div>
        <div class="header-user-section">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></span>
            </div>
            <a href="../../logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 800px; margin: 0 auto;">
            <h1 style="border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:20px;">
                <i class="fa-solid fa-user-plus"></i> Crear Nuevo Usuario
            </h1>

            <?php if (!empty($errores)): ?>
                <div class="mensaje error">
                    <ul><?php foreach ($errores as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form action="create.php" method="POST" class="crud-form">
                
                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="rut">RUT:</label>
                        <input type="text" id="rut" name="rut" value="<?php echo htmlspecialchars($rut); ?>" 
                            placeholder="12.345.678-9" required maxlength="12" oninput="darFormatoRut(this)">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="rol_id">Rol:</label>
                        <select id="rol_id" name="rol_id" required>
                            <option value="">Seleccione un rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['Id']; ?>" <?php echo ($rol['Id'] == $rol_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rol['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required maxlength="50">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="apellido">Apellido:</label>
                        <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" required maxlength="50">
                    </div>
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" maxlength="75">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="telefono">Teléfono:</label>
                        <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>" maxlength="30">
                    </div>
                </div>

                <div class="form-group">
                    <label for="contrasena">Contraseña:</label>
                    <div style="position: relative;">
                        <input type="password" id="contrasena" name="contrasena" placeholder="Ingrese contraseña" required maxlength="50" style="padding-right: 40px;">
                        <i class="fa-solid fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #9ca3af;"></i>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn-create" style="width: 100%; padding: 12px;">
                        <i class="fa-solid fa-save"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
        <footer class="main-footer">
            &copy; <?php echo date("Y"); ?> NutriData.
        </footer>
    </main>
    
    <script src="../../js/formato_rut.js"></script>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#contrasena');
        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>