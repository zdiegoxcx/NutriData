<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_estudiante = $_GET['id'] ?? null;
if (!$id_estudiante) {
    header("Location: ../../dashboard_admin_bd.php");
    exit;
}

// Obtener datos estudiante y del curso/establecimiento para volver
$sql = "SELECT e.*, c.Id AS Id_Curso, c.Id_Establecimiento 
        FROM Estudiante e 
        JOIN Curso c ON e.Id_Curso = c.Id 
        WHERE e.Id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$est = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$est) die("Estudiante no encontrado.");

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $fecha_nac = $_POST['fecha_nacimiento'];

    if (empty($rut) || empty($nombre)) $errores[] = "Datos incompletos.";

    if (empty($errores)) {
        try {
            $upd = $pdo->prepare("UPDATE Estudiante SET Rut=?, Nombre=?, Apellido=?, FechaNacimiento=? WHERE Id=?");
            $upd->execute([$rut, $nombre, $apellido, $fecha_nac, $id_estudiante]);

            $_SESSION['success_message'] = "Estudiante actualizado.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=".$est['Id_Establecimiento']."&id_curso=".$est['Id_Curso']);
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error: " . $e->getMessage();
        }
    }
} else {
    $rut = $est['Rut'];
    $nombre = $est['Nombre'];
    $apellido = $est['Apellido'];
    $fecha_nac = $est['FechaNacimiento'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Estudiante</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $est['Id_Establecimiento']; ?>&id_curso=<?php echo $est['Id_Curso']; ?>" class="nav-item active">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div></header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-pencil"></i> Editar Estudiante</h1>
                    <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

                    <form method="POST" class="crud-form">
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>RUT:</label>
                                <input type="text" name="rut" value="<?php echo htmlspecialchars($rut); ?>" required>
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
                            <button type="submit" class="btn-create" style="background:#ffc107; color:black;"><i class="fa-solid fa-save"></i> Actualizar</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>