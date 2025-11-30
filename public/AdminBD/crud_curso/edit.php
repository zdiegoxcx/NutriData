<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_curso = $_GET['id'] ?? null;
if (!$id_curso) {
    header("Location: ../../dashboard_admin_bd.php");
    exit;
}

// Obtener datos del curso
$stmt = $pdo->prepare("SELECT * FROM Curso WHERE Id = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    die("Curso no encontrado.");
}

$id_establecimiento = $curso['Id_Establecimiento']; // Para volver atrás
$profesores = $pdo->query("SELECT u.Id, u.Nombre, u.Apellido FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id WHERE r.Nombre = 'profesor' AND u.Estado = 1 ORDER BY u.Nombre")->fetchAll(PDO::FETCH_ASSOC);

$errores = [];
$nombre = $curso['Nombre'];
$profesor_id = $curso['Id_Profesor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $profesor_id = $_POST['profesor_id'];

    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($profesor_id)) $errores[] = "Debe asignar un profesor.";

    if (empty($errores)) {
        try {
            $update = $pdo->prepare("UPDATE Curso SET Nombre = ?, Id_Profesor = ? WHERE Id = ?");
            $update->execute([$nombre, $profesor_id, $id_curso]);
            
            $_SESSION['success_message'] = "Curso actualizado.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=" . $id_establecimiento);
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error DB: " . $e->getMessage();
        }
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
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $id_establecimiento; ?>" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div></header>
            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-pencil"></i> Editar Curso</h1>
                    <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>
                    
                    <form method="POST" class="crud-form">
                        <div class="form-group">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Profesor:</label>
                            <select name="profesor_id" required>
                                <?php foreach ($profesores as $p): ?>
                                    <option value="<?php echo $p['Id']; ?>" <?php echo ($p['Id'] == $profesor_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['Nombre'] . ' ' . $p['Apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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