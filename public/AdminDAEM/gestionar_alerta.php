<?php
session_start();
require_once __DIR__ . '/../../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN: SOLO ADMIN DAEM ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: ../login.php");
    exit;
}

$id_alerta = $_GET['id'] ?? null;
if (!$id_alerta) {
    header("Location: ../dashboard_admin_daem.php");
    exit;
}

// --- PROCESAR FORMULARIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_estado = $_POST['estado']; // 1 = Pendiente, 0 = Atendida
    $observaciones = trim($_POST['observaciones']);

    try {
        $stmt_update = $pdo->prepare("UPDATE Alerta SET Estado = ?, ObservacionesSeguimiento = ? WHERE Id = ?");
        $stmt_update->execute([$nuevo_estado, $observaciones, $id_alerta]);
        
        $_SESSION['success_message'] = "La alerta ha sido actualizada correctamente.";
        header("Location: ../dashboard_admin_daem.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// --- OBTENER DATOS DE LA ALERTA (GET) ---
// Hacemos JOINs para mostrar contexto completo: Quién es el alumno, qué colegio, etc.
// CAMBIO AQUÍ: CONCAT_WS para usar Nombres y Apellidos nuevos
$sql = "
    SELECT 
        a.Id, a.Nombre as TituloAlerta, a.Descripcion, a.Estado, a.ObservacionesSeguimiento,
        r.IMC, r.Peso, r.Altura, r.FechaMedicion,
        CONCAT_WS(' ', e.Nombres, e.ApellidoPaterno, e.ApellidoMaterno) as Estudiante,
        e.Rut,
        c.Nombre as Curso,
        est.Nombre as Establecimiento,
        u.Nombre as ProfeNombre, u.Apellido as ProfeApellido
    FROM Alerta a
    JOIN RegistroNutricional r ON a.Id_RegistroNutricional = r.Id
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
    JOIN Usuario u ON r.Id_Profesor = u.Id
    WHERE a.Id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_alerta]);
$alerta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alerta) die("Alerta no encontrada.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Alerta</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                 <a href="../dashboard_admin_daem.php" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver al Dashboard</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div>
            </header>

            <section class="content-body">
                <div class="content-container">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div>
                            <h1><i class="fa-solid fa-triangle-exclamation" style="color: #dc3545;"></i> Gestión de Caso de Riesgo</h1>
                            <p style="color: #666;">Folio Alerta: #<?php echo $alerta['Id']; ?></p>
                        </div>
                        <div style="text-align: right;">
                            <span class="status-<?php echo $alerta['Estado'] ? 'inactive' : 'active'; ?>" style="font-size: 1rem; padding: 8px 15px;">
                                <?php echo $alerta['Estado'] ? 'PENDIENTE' : 'ATENDIDA'; ?>
                            </span>
                        </div>
                    </div>

                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; margin-bottom: 30px;">
                        <h3 style="margin-bottom: 15px; color: #4361ee;">Datos del Estudiante</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div><strong>Nombre:</strong><br> <?php echo htmlspecialchars($alerta['Estudiante']); ?></div>
                            <div><strong>RUT:</strong><br> <?php echo htmlspecialchars($alerta['Rut']); ?></div>
                            <div><strong>Curso:</strong><br> <?php echo htmlspecialchars($alerta['Curso']); ?></div>
                            <div><strong>Establecimiento:</strong><br> <?php echo htmlspecialchars($alerta['Establecimiento']); ?></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 30px;">
                        <h3 style="margin-bottom: 15px;">Detalle de la Medición (<?php echo date("d/m/Y", strtotime($alerta['FechaMedicion'])); ?>)</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #eef2ff;">
                                    <th style="padding: 10px; border: 1px solid #ddd;">IMC</th>
                                    <th style="padding: 10px; border: 1px solid #ddd;">Peso</th>
                                    <th style="padding: 10px; border: 1px solid #ddd;">Altura</th>
                                    <th style="padding: 10px; border: 1px solid #ddd;">Profesor Evaluador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; color: #dc3545; text-align: center;">
                                        <?php echo $alerta['IMC']; ?>
                                    </td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo $alerta['Peso']; ?> kg</td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo $alerta['Altura']; ?> m</td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">
                                        <?php echo htmlspecialchars($alerta['ProfeNombre'] . ' ' . $alerta['ProfeApellido']); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p style="margin-top: 10px; font-style: italic; color: #555;">
                            <strong>Motivo Alerta:</strong> <?php echo htmlspecialchars($alerta['Descripcion']); ?>
                        </p>
                    </div>

                    <hr style="border: 0; border-top: 1px solid #ddd; margin: 30px 0;">

                    <form method="POST" class="crud-form">
                        <h3 style="color: #198754; margin-bottom: 15px;"><i class="fa-solid fa-clipboard-check"></i> Resolución del Caso</h3>
                        
                        <div class="form-group">
                            <label for="estado">Estado del Caso:</label>
                            <select name="estado" id="estado" style="max-width: 300px; padding: 10px; border-radius: 5px; font-weight: bold;">
                                <option value="1" <?php echo $alerta['Estado'] == 1 ? 'selected' : ''; ?>>🔴 Pendiente (Requiere acción)</option>
                                <option value="0" <?php echo $alerta['Estado'] == 0 ? 'selected' : ''; ?>>🟢 Atendida (Caso cerrado/derivado)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="observaciones">Observaciones de Seguimiento (DAEM):</label>
                            <textarea name="observaciones" id="observaciones" rows="4" 
                                placeholder="Ej: Se contactó al apoderado, se derivó a CESFAM, etc."
                                style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px;"
                            ><?php echo htmlspecialchars($alerta['ObservacionesSeguimiento'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-create" style="padding: 12px 25px; font-size: 1rem;">
                                <i class="fa-solid fa-save"></i> Guardar Gestión
                            </button>
                        </div>
                    </form>

                </div>
            </section>
        </main>
    </div>
</body>
</html>