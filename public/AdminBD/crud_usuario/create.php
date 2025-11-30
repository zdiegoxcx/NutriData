<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN DE LA PÁGINA ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$errores = [];
$rut = $nombre = $apellido = $email = $telefono = $contrasena = $rol_id = $estado = '';

// --- OBTENER ROLES PARA EL SELECT ---
$roles = $pdo->query("SELECT Id, Nombre FROM Rol ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir y limpiar datos
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $contrasena = $_POST['contrasena']; // Sin trim para contraseñas
    $rol_id = $_POST['rol_id'];
    $estado = $_POST['estado']; // 1 o 0

    // Validaciones básicas
    if (empty($rut)) { $errores[] = "El RUT es obligatorio."; }
    if (empty($nombre)) { $errores[] = "El nombre es obligatorio."; }
    if (empty($apellido)) { $errores[] = "El apellido es obligatorio."; }
    if (empty($contrasena)) { $errores[] = "La contraseña es obligatoria."; }
    if (empty($rol_id)) { $errores[] = "Debe asignar un rol."; }

    // Validar si el RUT ya existe
    if (empty($errores)) {
        $stmt_check = $pdo->prepare("SELECT Id FROM Usuario WHERE Rut = ?");
        $stmt_check->execute([$rut]);
        if ($stmt_check->rowCount() > 0) {
            $errores[] = "El RUT ingresado ya está registrado en el sistema.";
        }
    }

    if (empty($errores)) {
        try {
            $sql = "INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            // NOTA: Contraseña en texto plano como solicitaste. En prod usar password_hash()
            $stmt->execute([$rol_id, $rut, $nombre, $apellido, $contrasena, $telefono, $email, $estado]);

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
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DAEM NutriMonitor</h2>
            </div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=usuarios" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user">
                    <?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?>
                </div>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-user-plus"></i> Crear Nuevo Usuario</h1>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <ul>
                                <?php foreach ($errores as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="create.php" method="POST" class="crud-form">
                        
                        <!-- Fila 1: RUT y Rol -->
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="rut">RUT:</label>
                                <input type="text" id="rut" name="rut" value="<?php echo htmlspecialchars($rut); ?>" placeholder="12345678-9" required>
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

                        <!-- Fila 2: Nombre y Apellido -->
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="nombre">Nombre:</label>
                                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="apellido">Apellido:</label>
                                <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" required>
                            </div>
                        </div>

                        <!-- Fila 3: Email y Teléfono -->
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="telefono">Teléfono:</label>
                                <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>">
                            </div>
                        </div>

                        <!-- Fila 4: Contraseña y Estado -->
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="contrasena">Contraseña:</label>
                                <input type="password" id="contrasena" name="contrasena" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="estado">Estado:</label>
                                <select id="estado" name="estado" required>
                                    <option value="1" <?php echo ($estado === '1' || $estado === '') ? 'selected' : ''; ?>>Activo</option>
                                    <option value="0" <?php echo ($estado === '0') ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn-create" style="cursor:pointer;"><i class="fa-solid fa-save"></i> Guardar Usuario</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>