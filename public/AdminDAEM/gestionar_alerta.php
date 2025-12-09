<?php
session_start();
require_once __DIR__ . '/../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: ../login.php"); exit;
}

$id_alerta = $_GET['id'] ?? null;
if (!$id_alerta) { header("Location: ../dashboard_admin_daem.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_estado = $_POST['estado'];
    $observaciones = trim($_POST['observaciones']);
    $pdo->prepare("UPDATE Alerta SET Estado = ?, ObservacionesSeguimiento = ? WHERE Id = ?")->execute([$nuevo_estado, $observaciones, $id_alerta]);
    $_SESSION['success_message'] = "Alerta actualizada.";
    header("Location: ../dashboard_admin_daem.php"); exit;
}

$sql = "SELECT a.Id, a.Descripcion, a.Estado, a.ObservacionesSeguimiento, r.IMC, r.Peso, r.Altura, r.FechaMedicion, CONCAT_WS(' ', e.Nombres, e.ApellidoPaterno) as Estudiante, e.Rut, c.Nombre as Curso, est.Nombre as Establecimiento FROM Alerta a JOIN RegistroNutricional r ON a.Id_RegistroNutricional = r.Id JOIN Estudiante e ON r.Id_Estudiante = e.Id JOIN Curso c ON e.Id_Curso = c.Id JOIN Establecimiento est ON c.Id_Establecimiento = est.Id WHERE a.Id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_alerta]);
$alerta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$alerta) die("No encontrada.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Caso</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="../dashboard_admin_daem.php" class="btn-header-back">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">
                Gestión de Caso #<?php echo $alerta['Id']; ?>
            </div>
        </div>
        <div class="header-user-section">
            <div class="user-info"><span class="user-name"><?php echo $_SESSION['user_nombre']; ?></span><span class="user-role">DAEM</span></div>
            <a href="../logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 900px; margin: 0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 style="margin:0;"><i class="fa-solid fa-triangle-exclamation" style="color:#dc3545;"></i> Detalle del Caso</h1>
                <span class="status-<?php echo $alerta['Estado']?'inactive':'active'; ?>"><?php echo $alerta['Estado']?'PENDIENTE':'ATENDIDA'; ?></span>
            </div>

            <div style="background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #e9ecef;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div><strong>Estudiante:</strong> <?php echo htmlspecialchars($alerta['Estudiante']); ?></div>
                    <div><strong>RUT:</strong> <?php echo htmlspecialchars($alerta['Rut']); ?></div>
                    <div><strong>Colegio:</strong> <?php echo htmlspecialchars($alerta['Establecimiento']); ?></div>
                    <div><strong>Curso:</strong> <?php echo htmlspecialchars($alerta['Curso']); ?></div>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <h3 style="font-size:1.1rem; margin-bottom:10px;">Datos Clínicos</h3>
                <table style="width:100%; border:1px solid #ddd;">
                    <tr style="background:#eee;"><th>IMC</th><th>Peso</th><th>Altura</th><th>Fecha</th></tr>
                    <tr>
                        <td style="color:#dc3545; font-weight:bold; text-align:center;"><?php echo $alerta['IMC']; ?></td>
                        <td style="text-align:center;"><?php echo $alerta['Peso']; ?> kg</td>
                        <td style="text-align:center;"><?php echo $alerta['Altura']; ?> m</td>
                        <td style="text-align:center;"><?php echo date("d/m/Y", strtotime($alerta['FechaMedicion'])); ?></td>
                    </tr>
                </table>
                <p style="margin-top:10px; color:#666;"><em>Motivo: <?php echo htmlspecialchars($alerta['Descripcion']); ?></em></p>
            </div>

            <hr>

            <form method="POST">
                <div class="form-group">
                    <label>Estado de Gestión:</label>
                    <select name="estado" style="max-width:200px;">
                        <option value="1" <?php echo $alerta['Estado']==1?'selected':''; ?>>Pendiente</option>
                        <option value="0" <?php echo $alerta['Estado']==0?'selected':''; ?>>Atendida</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Observaciones de Seguimiento:</label>
                    <textarea name="observaciones" rows="4" placeholder="Acciones tomadas..."><?php echo htmlspecialchars($alerta['ObservacionesSeguimiento']??''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-create"><i class="fa-solid fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
        <footer class="main-footer">&copy; <?php echo date("Y"); ?> NutriData.</footer>
    </main>
</body>
</html>