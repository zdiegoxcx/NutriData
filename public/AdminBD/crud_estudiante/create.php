<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

// 1. Validar contexto del curso
$id_curso = $_GET['id_curso'] ?? null;
if (!$id_curso) {
    header("Location: ../../dashboard_admin_bd.php");
    exit;
}

// 2. Info para el botón volver
$stmt_info = $pdo->prepare("
    SELECT c.Nombre as Curso, e.Id as Id_Establecimiento, e.Nombre as Establecimiento 
    FROM Curso c 
    JOIN Establecimiento e ON c.Id_Establecimiento = e.Id 
    WHERE c.Id = ?
");
$stmt_info->execute([$id_curso]);
$contexto = $stmt_info->fetch(PDO::FETCH_ASSOC);
if (!$contexto) die("Curso no válido.");

$id_establecimiento = $contexto['Id_Establecimiento'];
$errores = [];
$rut = $nombres = $apellido_paterno = $apellido_materno = $fecha_nac = $sexo = '';

// 3. Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut']);
    $nombres = trim($_POST['nombres']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $sexo = $_POST['sexo']; 
    $fecha_nac = $_POST['fecha_nacimiento'];

    if (empty($rut)) $errores[] = "RUT obligatorio.";
    if (empty($nombres)) $errores[] = "Nombres obligatorios.";
    if (empty($apellido_paterno)) $errores[] = "Apellido Paterno obligatorio.";
    if (empty($sexo)) $errores[] = "Debe seleccionar el sexo.";

    // Validar duplicado
    $check = $pdo->prepare("SELECT Id FROM Estudiante WHERE Rut = ?");
    $check->execute([$rut]);
    if ($check->rowCount() > 0) $errores[] = "El RUT ya existe.";

    if (empty($errores)) {
        try {
            // INSERT ACTUALIZADO
            $sql = "INSERT INTO Estudiante (Id_Curso, Rut, Nombres, ApellidoPaterno, ApellidoMaterno, Sexo, FechaNacimiento, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_curso, $rut, $nombres, $apellido_paterno, $apellido_materno, $sexo, $fecha_nac]);
            
            $_SESSION['success_message'] = "Estudiante registrado exitosamente.";
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
    <title>Registrar Estudiante</title>
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
                    <h1><i class="fa-solid fa-user-plus"></i> Nuevo Estudiante</h1>
                    <p style="color:#666; margin-bottom:20px;">
                        Asignando a: <strong><?php echo htmlspecialchars($contexto['Curso']); ?></strong> 
                        (<?php echo htmlspecialchars($contexto['Establecimiento']); ?>)
                    </p>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error"><?php echo implode('<br>', $errores); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="crud-form">
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex:1;">
                                <label>RUT:</label>
                                <input type="text" name="rut" value="<?php echo htmlspecialchars($rut); ?>" placeholder="12345678-9" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Fecha Nacimiento:</label>
                                <input type="date" name="fecha_nacimiento" value="<?php echo $fecha_nac; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nombres:</label>
                            <input type="text" name="nombres" value="<?php echo htmlspecialchars($nombres); ?>" required>
                        </div>

                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Apellido Paterno:</label>
                                <input type="text" name="apellido_paterno" value="<?php echo htmlspecialchars($apellido_paterno); ?>" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Apellido Materno:</label>
                                <input type="text" name="apellido_materno" value="<?php echo htmlspecialchars($apellido_materno); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Sexo:</label>
                            <select name="sexo" required>
                                <option value="">Seleccione...</option>
                                <option value="M" <?php echo ($sexo == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="F" <?php echo ($sexo == 'F') ? 'selected' : ''; ?>>Femenino</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-create"><i class="fa-solid fa-save"></i> Guardar Estudiante</button>
                    </form>
                </div>
            </section>
        </main>
    </div>
</body>
</html>