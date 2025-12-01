<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: ../../dashboard_admin_bd.php"); exit; }

// Obtener datos (incluyendo Sexo)
$stmt = $pdo->prepare("SELECT e.*, c.Id_Establecimiento FROM Estudiante e JOIN Curso c ON e.Id_Curso = c.Id WHERE e.Id = ?");
$stmt->execute([$id]);
$est = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$est) die("Estudiante no encontrado.");

$id_curso = $est['Id_Curso'];
$id_establecimiento = $est['Id_Establecimiento'];

// Listado de cursos
$cursos = $pdo->prepare("SELECT Id, Nombre FROM Curso WHERE Id_Establecimiento = ? ORDER BY Nombre");
$cursos->execute([$id_establecimiento]);
$lista_cursos = $cursos->fetchAll(PDO::FETCH_ASSOC);

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $rut = trim($_POST['rut']);
    $sexo = $_POST['sexo']; // NUEVO
    $fecha_nac = $_POST['fecha_nacimiento'];
    $nuevo_id_curso = $_POST['id_curso'];
    $estado = $_POST['estado'];

    if (empty($rut) || empty($nombre) || empty($sexo)) $errores[] = "Datos incompletos.";

    if (empty($errores)) {
        try {
            // UPDATE INCLUYENDO SEXO
            $sql = "UPDATE Estudiante SET Nombre=?, Apellido=?, Rut=?, Sexo=?, FechaNacimiento=?, Id_Curso=?, Estado=? WHERE Id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $apellido, $rut, $sexo, $fecha_nac, $nuevo_id_curso, $estado, $id]);
            
            $_SESSION['success_message'] = "Estudiante actualizado.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_establecimiento&id_curso=$nuevo_id_curso");
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
    <title>Editar Estudiante</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>NutriMonitor</h2></div>
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
                    <h1><i class="fa-solid fa-user-pen"></i> Editar Estudiante</h1>
                    <?php if (!empty($errores)): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>
                    
                    <form method="POST" class="crud-form">
                        <div class="form-group">
                            <label>RUT:</label>
                            <input type="text" name="rut" value="<?php echo htmlspecialchars($est['Rut']); ?>" required>
                        </div>
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex:1;"><label>Nombre:</label><input type="text" name="nombre" value="<?php echo htmlspecialchars($est['Nombre']); ?>" required></div>
                            <div class="form-group" style="flex:1;"><label>Apellido:</label><input type="text" name="apellido" value="<?php echo htmlspecialchars($est['Apellido']); ?>" required></div>
                        </div>
                        
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Fecha Nacimiento:</label>
                                <input type="date" name="fecha_nacimiento" value="<?php echo $est['FechaNacimiento']; ?>" required>
                            </div>
                            
                            <div class="form-group" style="flex:1;">
                                <label>Sexo:</label>
                                <select name="sexo" required>
                                    <option value="M" <?php echo ($est['Sexo'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="F" <?php echo ($est['Sexo'] == 'F') ? 'selected' : ''; ?>>Femenino</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Curso:</label>
                            <select name="id_curso" required>
                                <?php foreach ($lista_cursos as $c): ?>
                                    <option value="<?php echo $c['Id']; ?>" <?php echo ($c['Id'] == $est['Id_Curso']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['Nombre']); ?>
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