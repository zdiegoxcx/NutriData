<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_establecimiento = $_GET['id_establecimiento'] ?? null;
if (!$id_establecimiento) {
    header("Location: ../../dashboard_admin_bd.php?vista=estudiantes");
    exit;
}

$errores = [];
$nombre = $profesor_id = '';

// --- OBTENER LISTA DE PROFESORES ---
// Buscamos usuarios cuyo rol sea 'profesor'
$sql_profes = "SELECT u.Id, u.Nombre, u.Apellido 
               FROM Usuario u
               JOIN Rol r ON u.Id_Rol = r.Id
               WHERE r.Nombre = 'profesor' AND u.Estado = 1
               ORDER BY u.Nombre";
$profesores = $pdo->query($sql_profes)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $profesor_id = $_POST['profesor_id'];

    if (empty($nombre)) { $errores[] = "El nombre del curso es obligatorio."; }
    if (empty($profesor_id)) { $errores[] = "Debe asignar un profesor encargado."; }

    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor) VALUES (?, ?, ?)");
            $stmt->execute([$id_establecimiento, $nombre, $profesor_id]);

            $_SESSION['success_message'] = "Curso creado exitosamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=" . $id_establecimiento);
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error al crear curso: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Curso</title>
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
                 <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $id_establecimiento; ?>" class="nav-item active">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></div>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-chalkboard-user"></i> Crear Nuevo Curso</h1>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <ul><?php foreach ($errores as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form action="create.php?id_establecimiento=<?php echo $id_establecimiento; ?>" method="POST" class="crud-form">
                        
                        <div class="form-group">
                            <label for="nombre">Nombre del Curso:</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" placeholder="Ej: 1° Básico A" required>
                        </div>

                        <div class="form-group">
                            <label for="profesor_id">Profesor Encargado:</label>
                            <select id="profesor_id" name="profesor_id" required>
                                <option value="">Seleccione un profesor</option>
                                <?php foreach ($profesores as $profe): ?>
                                    <option value="<?php echo $profe['Id']; ?>" <?php echo ($profe['Id'] == $profesor_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($profe['Nombre'] . ' ' . $profe['Apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if(empty($profesores)): ?>
                                <small style="color: #dc3545;">* No hay profesores activos registrados.</small>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn-create" style="cursor:pointer;"><i class="fa-solid fa-save"></i> Guardar Curso</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>