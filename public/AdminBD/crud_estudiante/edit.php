<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) header("Location: ../../dashboard_admin_bd.php");

$stmt = $pdo->prepare("SELECT * FROM Estudiante WHERE Id = ?");
$stmt->execute([$id]);
$est = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$est) die("Estudiante no encontrado.");

$cursos = $pdo->query("SELECT c.Id, c.Nombre, e.Nombre as Establecimiento FROM Curso c JOIN Establecimiento e ON c.Id_Establecimiento = e.Id ORDER BY e.Nombre, c.Nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $rut = $_POST['rut'];
    $fecha_nac = $_POST['fecha_nacimiento'];
    $id_curso = $_POST['id_curso'];
    $estado = $_POST['estado'];

    try {
        $sql = "UPDATE Estudiante SET Nombre=?, Apellido=?, Rut=?, FechaNacimiento=?, Id_Curso=?, Estado=? WHERE Id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $apellido, $rut, $fecha_nac, $id_curso, $estado, $id]);
        
        // Recuperar ID establecimiento para redirección correcta
        $est_data = $pdo->query("SELECT Id_Establecimiento FROM Curso WHERE Id = $id_curso")->fetch();
        
        $_SESSION['success_message'] = "Estudiante actualizado.";
        header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=".$est_data['Id_Establecimiento']."&id_curso=".$id_curso);
        exit;
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
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
            <div class="sidebar-header"><h2>NutriMonitor</h2></div>
            <nav class="sidebar-nav"><a href="../../dashboard_admin_bd.php?vista=estudiantes" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a></nav>
        </aside>
        <main class="main-content">
            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-user-pen"></i> Editar Estudiante</h1>
                    <form method="POST" class="crud-form">
                        <div class="form-group">
                            <label>RUT:</label>
                            <input type="text" name="rut" value="<?php echo htmlspecialchars($est['Rut']); ?>" required>
                        </div>
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex:1;"><label>Nombre:</label><input type="text" name="nombre" value="<?php echo htmlspecialchars($est['Nombre']); ?>" required></div>
                            <div class="form-group" style="flex:1;"><label>Apellido:</label><input type="text" name="apellido" value="<?php echo htmlspecialchars($est['Apellido']); ?>" required></div>
                        </div>
                        <div class="form-group">
                            <label>Fecha Nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" value="<?php echo $est['FechaNacimiento']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Curso:</label>
                            <select name="id_curso" required>
                                <?php foreach ($cursos as $c): ?>
                                    <option value="<?php echo $c['Id']; ?>" <?php echo ($c['Id'] == $est['Id_Curso']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['Establecimiento'] . " - " . $c['Nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado">
                                <option value="1" <?php echo ($est['Estado'] == 1) ? 'selected' : ''; ?>>Activo</option>
                                <option value="0" <?php echo ($est['Estado'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-create"><i class="fa-solid fa-save"></i> Actualizar</button>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>