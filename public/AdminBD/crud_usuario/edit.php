<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) { header("Location: ../../dashboard_admin_bd.php?vista=usuarios"); exit; }

// Obtener datos
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

    if (empty($rut) || empty($nombre) || empty($rol_id)) { 
        $errores[] = "Campos obligatorios faltantes."; 
    } else {
        try {
            if (!empty($nueva_contrasena)) {
                // ENCRIPTAR LA NUEVA CONTRASEÑA
                $pass_hashed = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

                $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=?, Contraseña=? WHERE Id=?";
                // Usamos $pass_hashed
                $pdo->prepare($sql)->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $pass_hashed, $id_usuario]);
            } else {
                $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=? WHERE Id=?";
                $pdo->prepare($sql)->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $id_usuario]);
            }
            $_SESSION['success_message'] = "Usuario actualizado.";
            header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error: " . $e->getMessage();
        }
    }
} else {
    $rut = $usuario['Rut']; $nombre = $usuario['Nombre']; $apellido = $usuario['Apellido'];
    $email = $usuario['Email']; $telefono = $usuario['Telefono']; $rol_id = $usuario['Id_Rol'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=usuarios" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></div>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <?php if ($es_activo): ?>
                        <h1><i class="fa-solid fa-user-pen"></i> Editar Usuario</h1>
                    <?php else: ?>
                        <h1 style="color: #6c757d;"><i class="fa-solid fa-user-lock"></i> Usuario Inactivo</h1>
                    <?php endif; ?>

                    <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

                    <form method="POST" class="crud-form">
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;"><label>RUT:</label><input type="text" name="rut" value="<?php echo htmlspecialchars($rut); ?>" <?php echo $readonly_attr; ?>></div>
                            <div class="form-group" style="flex:1;">
                                <label>Rol:</label>
                                <select name="rol_id" <?php echo $readonly_attr; ?>>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['Id']; ?>" <?php echo ($r['Id'] == $rol_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['Nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;"><label>Nombre:</label><input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" <?php echo $readonly_attr; ?>></div>
                            <div class="form-group" style="flex:1;"><label>Apellido:</label><input type="text" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" <?php echo $readonly_attr; ?>></div>
                        </div>
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;"><label>Email:</label><input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" <?php echo $readonly_attr; ?>></div>
                            <div class="form-group" style="flex:1;"><label>Teléfono:</label><input type="text" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>" <?php echo $readonly_attr; ?>></div>
                        </div>
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;"><label>Contraseña:</label><input type="password" name="contrasena" placeholder="Dejar en blanco para mantener" <?php echo $readonly_attr; ?>></div>
                            <div class="form-group" style="flex:1;">
                                <label>Estado:</label>
                                <?php if ($es_activo): ?>
                                    <div style="padding:10px; background:#d1e7dd; color:#0f5132; border-radius:4px;">Activo</div>
                                <?php else: ?>
                                    <div style="padding:10px; background:#f8d7da; color:#842029; border-radius:4px;">
                                        Inactivo (Eliminado el <?php echo $usuario['FechaEliminacion']; ?>)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($es_activo): ?>
                            <div class="form-actions" style="margin-top:30px;">
                                <button type="submit" class="btn-create" style="background-color:#ffc107; color:#000;"><i class="fa-solid fa-save"></i> Actualizar</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>