<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
require_once __DIR__ . '/../../../src/config/validaciones.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) { header("Location: ../../dashboard_admin_bd.php?vista=usuarios"); exit; }

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM Usuario WHERE Id = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) { header("Location: ../../dashboard_admin_bd.php?vista=usuarios"); exit; }

$es_activo = ($usuario['Estado'] == 1);
$readonly_attr = $es_activo ? '' : 'disabled';
$roles = $pdo->query("SELECT Id, Nombre FROM Rol ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$es_activo) {
        $_SESSION['error'] = "Usuario inactivo no editable.";
        header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
        exit;
    }

    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $nueva_contrasena = $_POST['contrasena'];
    $rol_id = $_POST['rol_id'];

    if (!validarRut($rut)) $errores[] = "El RUT ingresado no es válido.";
    if (!validarSoloLetras($nombre)) $errores[] = "El nombre solo puede contener letras.";
    if (!validarSoloLetras($apellido)) $errores[] = "El apellido solo puede contener letras.";
    if (empty($rut) || empty($nombre) || empty($rol_id)) $errores[] = "Campos obligatorios faltantes."; 

    if (empty($errores)) {
        try {
            if (!empty($nueva_contrasena)) {
                $pass_hashed = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
                $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=?, Contraseña=? WHERE Id=?";
                $pdo->prepare($sql)->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $pass_hashed, $id_usuario]);
            } else {
                $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=? WHERE Id=?";
                $pdo->prepare($sql)->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $id_usuario]);
            }
            $_SESSION['success_message'] = "Usuario actualizado correctamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error: " . $e->getMessage();
        }
    }
} else {
    // Pre-llenar datos para la vista GET
    $rut = $usuario['Rut']; $nombre = $usuario['Nombre']; $apellido = $usuario['Apellido'];
    $email = $usuario['Email']; $telefono = $usuario['Telefono']; $rol_id = $usuario['Id_Rol'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
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
                <i class="fa-solid fa-user-pen"></i> Editar Usuario
            </h1>

            <?php if (!$es_activo): ?>
                <div class="mensaje error"><i class="fa-solid fa-user-lock"></i> Usuario Inactivo (Solo lectura).</div>
            <?php endif; ?>

            <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

            <form method="POST" class="crud-form">
                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;">
                        <label>RUT:</label>
                        <input type="text" name="rut" value="<?php echo htmlspecialchars($rut); ?>" 
                               required maxlength="12" oninput="darFormatoRut(this)" <?php echo $readonly_attr; ?>>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Rol:</label>
                        <select name="rol_id" <?php echo $readonly_attr; ?>>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['Id']; ?>" <?php echo ($r['Id'] == $rol_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required maxlength="50" <?php echo $readonly_attr; ?>>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Apellido:</label>
                        <input type="text" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" required maxlength="50" <?php echo $readonly_attr; ?>>
                    </div>
                </div>

                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" maxlength="75" <?php echo $readonly_attr; ?>>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Teléfono:</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>" maxlength="30" <?php echo $readonly_attr; ?>>
                    </div>
                </div>

                <div class="form-group">
                    <label>Contraseña:</label>
                    <input type="password" name="contrasena" placeholder="Dejar en blanco para mantener la actual" <?php echo $readonly_attr; ?>>
                </div>

                <?php if ($es_activo): ?>
                    <div class="form-actions" style="margin-top:30px;">
                        <button type="submit" class="btn-create" style="background-color:#ffc107; color:#000; width:100%;">
                            <i class="fa-solid fa-save"></i> Actualizar Datos
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <footer class="main-footer">
            &copy; <?php echo date("Y"); ?> NutriData.
        </footer>
    </main>
    <script src="../../js/formato_rut.js"></script>
</body>
</html>