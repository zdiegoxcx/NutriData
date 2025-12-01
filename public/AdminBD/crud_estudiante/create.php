<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_curso = $_GET['id_curso'] ?? null;
if (!$id_curso) {
    header("Location: ../../dashboard_admin_bd.php");
    exit;
}

// Obtener ID del establecimiento para el botón "Volver"
$stmt_info = $pdo->prepare("SELECT Id_Establecimiento FROM Curso WHERE Id = ?");
$stmt_info->execute([$id_curso]);
$id_establecimiento = $stmt_info->fetchColumn();

$errores = [];
$rut = $nombre = $apellido = $fecha_nac = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $fecha_nac = $_POST['fecha_nacimiento'];

    if (empty($rut)) $errores[] = "RUT obligatorio.";
    if (empty($nombre)) $errores[] = "Nombre obligatorio.";
    
    // Validar RUT único (Opcional, pero recomendado)
    $check = $pdo->prepare("SELECT Id FROM Estudiante WHERE Rut = ?");
    $check->execute([$rut]);
    if ($check->rowCount() > 0) $errores[] = "El RUT ya existe.";

    if (empty($errores)) {
        try {
            // INSERT con Estado = 1 (Automático)
            $sql = "INSERT INTO Estudiante (Id_Curso, Rut, Nombre, Apellido, FechaNacimiento, Estado) VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_curso, $rut, $nombre, $apellido, $fecha_nac]);

            $_SESSION['success_message'] = "Estudiante creado.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_establecimiento&id_curso=$id_curso");
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Estudiante</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $id_establecimiento; ?>&id_curso=<?php echo $id_curso; ?>" class="nav-item active">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div></header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-child"></i> Nuevo Estudiante</h1>
                    <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

                    <form method="POST" class="crud-form">
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>RUT:</label>
                                <input type="text" name="rut" value="<?php echo htmlspecialchars($rut); ?>" placeholder="12345678-9" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Fecha Nacimiento:</label>
                                <input type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars($fecha_nac); ?>" required>
                            </div>
                        </div>
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Nombre:</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Apellido:</label>
                                <input type="text" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-actions" style="margin-top:20px;">
                            <button type="submit" class="btn-create"><i class="fa-solid fa-save"></i> Guardar Estudiante</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>