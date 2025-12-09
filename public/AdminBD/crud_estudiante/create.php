<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
require_once __DIR__ . '/../../../src/config/validaciones.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php"); exit;
}

$id_curso = $_GET['id_curso'] ?? null;
if (!$id_curso) { header("Location: ../../dashboard_admin_bd.php"); exit; }

$stmt = $pdo->prepare("SELECT c.Nombre as Curso, e.Id as Id_Establecimiento, e.Nombre as Establecimiento FROM Curso c JOIN Establecimiento e ON c.Id_Establecimiento = e.Id WHERE c.Id = ?");
$stmt->execute([$id_curso]);
$contexto = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contexto) die("Curso no válido.");

$id_est = $contexto['Id_Establecimiento'];
$errores = []; $rut = $nombres = $ape_p = $ape_m = $sexo = $fecha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut']); $nombres = trim($_POST['nombres']);
    $ape_p = trim($_POST['ape_p']); $ape_m = trim($_POST['ape_m']);
    $sexo = $_POST['sexo']; $fecha = $_POST['fecha'];

    if (!validarRut($rut)) $errores[] = "RUT inválido.";
    if (empty($nombres) || empty($ape_p) || empty($sexo) || empty($fecha)) $errores[] = "Faltan datos.";
    
    $check = $pdo->prepare("SELECT Id FROM Estudiante WHERE Rut = ?");
    $check->execute([$rut]);
    if ($check->rowCount() > 0) $errores[] = "El RUT ya existe.";

    if (empty($errores)) {
        try {
            $sql = "INSERT INTO Estudiante (Id_Curso, Rut, Nombres, ApellidoPaterno, ApellidoMaterno, Sexo, FechaNacimiento, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $pdo->prepare($sql)->execute([$id_curso, $rut, $nombres, $ape_p, $ape_m, $sexo, $fecha]);
            $_SESSION['success_message'] = "Estudiante registrado.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_est&id_curso=$id_curso"); exit;
        } catch (PDOException $e) { $errores[] = "Error: " . $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Estudiante</title>
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $id_est; ?>&id_curso=<?php echo $id_curso; ?>" class="btn-header-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">Admin BD</div>
        </div>
        <div class="header-user-section">
            <div class="user-info"><span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></span></div>
            <a href="../../logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 800px; margin: 0 auto;">
            <h1 style="border-bottom:1px solid #eee; padding-bottom:10px;">Nuevo Estudiante</h1>
            <p style="color:#666;">Curso: <strong><?php echo $contexto['Curso']; ?></strong> - <?php echo $contexto['Establecimiento']; ?></p>
            
            <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

            <form method="POST" class="crud-form">
                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;"><label>RUT:</label><input type="text" name="rut" value="<?php echo htmlspecialchars($rut); ?>" oninput="darFormatoRut(this)" required></div>
                    <div class="form-group" style="flex:1;"><label>Fecha Nacimiento:</label><input type="date" name="fecha" value="<?php echo $fecha; ?>" required></div>
                </div>
                <div class="form-group"><label>Nombres:</label><input type="text" name="nombres" value="<?php echo htmlspecialchars($nombres); ?>" required></div>
                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;"><label>Apellido Paterno:</label><input type="text" name="ape_p" value="<?php echo htmlspecialchars($ape_p); ?>" required></div>
                    <div class="form-group" style="flex:1;"><label>Apellido Materno:</label><input type="text" name="ape_m" value="<?php echo htmlspecialchars($ape_m); ?>"></div>
                </div>
                <div class="form-group"><label>Sexo:</label>
                    <select name="sexo" required>
                        <option value="">Seleccione...</option>
                        <option value="M" <?php if($sexo=='M') echo 'selected'; ?>>Masculino</option>
                        <option value="F" <?php if($sexo=='F') echo 'selected'; ?>>Femenino</option>
                    </select>
                </div>
                <div class="form-actions" style="margin-top:20px;">
                    <button type="submit" class="btn-create" style="width:100%;">Guardar</button>
                </div>
            </form>
        </div>
        <footer class="main-footer">&copy; <?php echo date("Y"); ?> NutriData.</footer>
    </main>
    <script src="../../js/formato_rut.js"></script>
</body>
</html>