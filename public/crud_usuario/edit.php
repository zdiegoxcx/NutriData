<?php
session_start();
require_once __DIR__ . '/../../src/config/db.php'; 
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../login.php");
    exit;
}

$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) {
    header("Location: ../dashboard_admin_bd.php?vista=usuarios");
    exit;
}

$errores = [];

// --- 1. OBTENER DATOS ACTUALES ---
try {
    $stmt = $pdo->prepare("SELECT * FROM Usuario WHERE Id = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $_SESSION['error'] = "Usuario no encontrado.";
        header("Location: ../dashboard_admin_bd.php?vista=usuarios");
        exit;
    }
} catch (PDOException $e) {
    die("Error al cargar usuario: " . $e->getMessage());
}

// --- 2. OBTENER ROLES ---
$roles = $pdo->query("SELECT Id, Nombre FROM Rol ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

// --- 3. PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $nueva_contrasena = $_POST['contrasena']; // Puede estar vacía
    $rol_id = $_POST['rol_id'];
    $estado = $_POST['estado'];

    if (empty($rut)) { $errores[] = "El RUT es obligatorio."; }
    if (empty($nombre)) { $errores[] = "El nombre es obligatorio."; }
    if (empty($apellido)) { $errores[] = "El apellido es obligatorio."; }
    if (empty($rol_id)) { $errores[] = "Debe asignar un rol."; }

    if (empty($errores)) {
        try {
            if (!empty($nueva_contrasena)) {
                // ACTUALIZAR CON CONTRASEÑA
                $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=?, Estado=?, Contraseña=? WHERE Id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $estado, $nueva_contrasena, $id_usuario]);
            } else {
                // ACTUALIZAR SIN TOCAR LA CONTRASEÑA
                $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=?, Estado=? WHERE Id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $estado, $id_usuario]);
            }

            $_SESSION['success_message'] = "Usuario actualizado correctamente.";
            header("Location: ../dashboard_admin_bd.php?vista=usuarios");
            exit;

        } catch (PDOException $e) {
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
} else {
    // Si es GET, precargar variables para el value="" de los inputs
    $rut = $usuario['Rut'];
    $nombre = $usuario['Nombre'];
    $apellido = $usuario['Apellido'];
    $email = $usuario['Email'];
    $telefono = $usuario['Telefono'];
    $rol_id = $usuario['Id_Rol'];
    $estado = $usuario['Estado'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DAEM NutriMonitor</h2>
            </div>
            <nav class="sidebar-nav">
                 <a href="../dashboard_admin_bd.php?vista=usuarios" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
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
                    <h1><i class="fa-solid fa-user-pen"></i> Editar Usuario</h1>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <ul>
                                <?php foreach ($errores as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="edit.php?id=<?php echo $id_usuario; ?>" method="POST" class="crud-form">
                        
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="rut">RUT:</label>
                                <input type="text" id="rut" name="rut" value="<?php echo htmlspecialchars($rut); ?>" required>
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
                                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="apellido">Apellido:</label>
                                <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" required>
                            </div>
                        </div>

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

                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="contrasena">Contraseña:</label>
                                <input type="password" id="contrasena" name="contrasena" placeholder="Dejar en blanco para mantener la actual">
                                <small style="color: #666;">Solo rellene si desea cambiarla.</small>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="estado">Estado:</label>
                                <select id="estado" name="estado" required>
                                    <option value="1" <?php echo ($estado == 1) ? 'selected' : ''; ?>>Activo</option>
                                    <option value="0" <?php echo ($estado == 0) ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn-create" style="background-color: #ffc107; color: #000; cursor: pointer;"><i class="fa-solid fa-save"></i> Actualizar Usuario</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>