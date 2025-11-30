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

// Cargar datos actuales
$stmt = $pdo->prepare("SELECT * FROM Curso WHERE Id = ?");
$stmt->execute([$id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$curso) die("Curso no encontrado.");

// Listas para selects
$establecimientos = $pdo->query("SELECT Id, Nombre FROM Establecimiento ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
$profesores = $pdo->query("SELECT u.Id, u.Nombre, u.Apellido FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id WHERE r.Nombre = 'profesor' ORDER BY u.Nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $id_est = $_POST['id_establecimiento'];
    $id_prof = $_POST['id_profesor'];

    try {
        $upd = $pdo->prepare("UPDATE Curso SET Nombre=?, Id_Establecimiento=?, Id_Profesor=? WHERE Id=?");
        $upd->execute([$nombre, $id_est, $id_prof, $id]);
        $_SESSION['success_message'] = "Curso actualizado.";
        header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=" . $id_est);
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
    <title>Editar Curso</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=estudiantes" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div></header>
            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-pencil"></i> Editar Curso</h1>
                    <form method="POST" class="crud-form">
                        <div class="form-group">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($curso['Nombre']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Establecimiento:</label>
                            <select name="id_establecimiento" required>
                                <?php foreach ($establecimientos as $est): ?>
                                    <option value="<?php echo $est['Id']; ?>" <?php echo ($est['Id'] == $curso['Id_Establecimiento']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['Nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Profesor:</label>
                            <select name="id_profesor" required>
                                <?php foreach ($profesores as $prof): ?>
                                    <option value="<?php echo $prof['Id']; ?>" <?php echo ($prof['Id'] == $curso['Id_Profesor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prof['Nombre'] . " " . $prof['Apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
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