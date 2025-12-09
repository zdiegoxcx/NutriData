<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
require_once __DIR__ . '/../../../src/config/validaciones.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php"); exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: ../../dashboard_admin_bd.php"); exit; }

$stmt = $pdo->prepare("SELECT e.*, c.Id_Establecimiento, c.Nombre as CursoNombre FROM Estudiante e JOIN Curso c ON e.Id_Curso = c.Id WHERE e.Id = ?");
$stmt->execute([$id]);
$est = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$est) die("Estudiante no encontrado.");

$id_est_colegio = $est['Id_Establecimiento'];
$id_curso_actual = $est['Id_Curso'];
$es_activo = ($est['Estado'] == 1);
$errores = [];

$cursos = $pdo->prepare("SELECT Id, Nombre FROM Curso WHERE Id_Establecimiento = ? AND Estado=1 ORDER BY Nombre");
$cursos->execute([$id_est_colegio]);
$lista_cursos = $cursos->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_activo) {
    $rut = trim($_POST['rut']); $nombres = trim($_POST['nombres']);
    $ape_p = trim($_POST['ape_p']); $ape_m = trim($_POST['ape_m']);
    $sexo = $_POST['sexo']; $fecha = $_POST['fecha']; $nuevo_curso = $_POST['id_curso'];

    if (!validarRut($rut)) $errores[] = "RUT inválido.";
    if (empty($nombres) || empty($ape_p)) $errores[] = "Faltan datos.";

    if (empty($errores)) {
        try {
            $sql = "UPDATE Estudiante SET Nombres=?, ApellidoPaterno=?, ApellidoMaterno=?, Rut=?, Sexo=?, FechaNacimiento=?, Id_Curso=? WHERE Id=?";
            $pdo->prepare($sql)->execute([$nombres, $ape_p, $ape_m, $rut, $sexo, $fecha, $nuevo_curso, $id]);
            $_SESSION['success_message'] = "Actualizado correctamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_est_colegio&id_curso=$nuevo_curso"); exit;
        } catch (PDOException $e) { $errores[] = "Error: " . $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Estudiante</title>
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $id_est_colegio; ?>&id_curso=<?php echo $id_curso_actual; ?>" class="btn-header-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">Admin BD</div>
        </div>
        <div class="header-user-section">
            <div class="user-info"><span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></span></div>
            <a href="../../logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 800px; margin: 0 auto;">
            <h1 style="border-bottom:1px solid #eee; padding-bottom:10px;">Editar Estudiante</h1>
            <?php if (!$es_activo): ?><div class="mensaje error">Registro inactivo (Papelera).</div><?php endif; ?>
            <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

            <form method="POST" class="crud-form">
                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;"><label>RUT:</label><input type="text" name="rut" value="<?php echo htmlspecialchars($est['Rut']); ?>" oninput="darFormatoRut(this)" required <?php if(!$es_activo) echo 'disabled'; ?>></div>
                    <div class="form-group" style="flex:1;"><label>Fecha Nacimiento:</label><input type="date" name="fecha" value="<?php echo $est['FechaNacimiento']; ?>" required <?php if(!$es_activo) echo 'disabled'; ?>></div>
                </div>
                <div class="form-group"><label>Nombres:</label><input type="text" name="nombres" value="<?php echo htmlspecialchars($est['Nombres']); ?>" required <?php if(!$es_activo) echo 'disabled'; ?>></div>
                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;"><label>Apellido Paterno:</label><input type="text" name="ape_p" value="<?php echo htmlspecialchars($est['ApellidoPaterno']); ?>" required <?php if(!$es_activo) echo 'disabled'; ?>></div>
                    <div class="form-group" style="flex:1;"><label>Apellido Materno:</label><input type="text" name="ape_m" value="<?php echo htmlspecialchars($est['ApellidoMaterno']); ?>" <?php if(!$es_activo) echo 'disabled'; ?>></div>
                </div>
                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;"><label>Sexo:</label>
                        <select name="sexo" required <?php if(!$es_activo) echo 'disabled'; ?>>
                            <option value="M" <?php echo ($est['Sexo']=='M'?'selected':''); ?>>Masculino</option>
                            <option value="F" <?php echo ($est['Sexo']=='F'?'selected':''); ?>>Femenino</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;"><label>Curso:</label>
                        <select name="id_curso" required <?php if(!$es_activo) echo 'disabled'; ?>>
                            <?php foreach($lista_cursos as $c): ?>
                                <option value="<?php echo $c['Id']; ?>" <?php echo ($c['Id']==$id_curso_actual?'selected':''); ?>><?php echo htmlspecialchars($c['Nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if ($es_activo): ?>
                <div class="form-actions" style="margin-top:20px;">
                    <button type="submit" class="btn-create" style="width:100%; background:#ffc107; color:black;">Actualizar</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <footer class="main-footer">&copy; <?php echo date("Y"); ?> NutriData.</footer>
    </main>
    <script src="../../js/formato_rut.js"></script>
</body>
</html>