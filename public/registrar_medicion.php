<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
require_once __DIR__ . '/../src/enviar_alerta.php';

$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'profesor') {
    header("Location: login.php"); exit;
}

$id_estudiante = $_GET['id_estudiante'] ?? null;
// ... (Lógica PHP original intacta) ...
// (Mantenemos tu lógica de obtención de datos y procesamiento POST tal cual)
if ($id_estudiante) {
    $sql_est = "SELECT e.Nombres, e.ApellidoPaterno, e.ApellidoMaterno, e.Rut, e.FechaNacimiento, e.Id_Curso, 
                c.Nombre as NombreCurso, est.Nombre as NombreEstablecimiento
                FROM Estudiante e
                JOIN Curso c ON e.Id_Curso = c.Id
                JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
                WHERE e.Id = ?";
    $stmt = $pdo->prepare($sql_est);
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$estudiante) die("Estudiante no encontrado.");
} else {
    header("Location: dashboard_profesor.php"); exit;
}

function calcularDiagnostico($imc, $edad) {
    if ($edad >= 19) {
        if ($imc < 18.5) return 'Bajo Peso';
        if ($imc >= 18.5 && $imc < 25) return 'Normal';
        if ($imc >= 25.0 && $imc < 30) return 'Sobrepeso';
        if ($imc >= 30.0 && $imc < 35) return 'Obesidad Grado I';
        if ($imc >= 35.0 && $imc < 40) return 'Obesidad Grado II';
        if ($imc >= 40.0) return 'Obesidad Grado III';
    } else {
        if ($edad <= 13) {
            if ($imc < 14.5) return 'Bajo Peso';
            if ($imc >= 14.5 && $imc < 20) return 'Normal'; 
            if ($imc >= 20 && $imc < 23) return 'Sobrepeso';
            if ($imc >= 23 && $imc < 26) return 'Obesidad';
            if ($imc >= 26) return 'Obesidad Severa';
        } else {
            if ($imc < 17.5) return 'Bajo Peso';
            if ($imc >= 17.5 && $imc < 24) return 'Normal';
            if ($imc >= 24 && $imc < 28) return 'Sobrepeso';
            if ($imc >= 28 && $imc < 32) return 'Obesidad'; 
            if ($imc >= 32) return 'Obesidad Severa';
        }
    }
    return 'Normal'; 
}

$errores = []; $mensaje = ''; $tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peso_bruto = floatval($_POST['peso']);
    $altura = floatval($_POST['altura']);
    $motivo_descuento = trim($_POST['motivo_descuento']);
    $peso_descuento = floatval($_POST['peso_descuento']);
    $observaciones = trim($_POST['observaciones']);

    if ($altura < 0.50 || $altura > 2.10) $errores[] = "La altura debe estar entre 0.50m y 2.10m.";
    if ($peso_bruto <= 0 || $peso_bruto > 200) $errores[] = "El peso debe ser mayor a 0 y máximo 200kg.";
    $peso_real = $peso_bruto - $peso_descuento;
    if ($peso_real <= 0) $errores[] = "El peso final no puede ser cero o negativo.";

    if (empty($errores)) {
        try {
            $imc = round($peso_real / ($altura * $altura), 2);
            $fecha_nac = new DateTime($estudiante['FechaNacimiento']);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;
            $diagnostico = calcularDiagnostico($imc, $edad);

            $pdo->beginTransaction();
            $sql = "INSERT INTO RegistroNutricional (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, Diagnostico, FechaMedicion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
            $pdo->prepare($sql)->execute([$_SESSION['user_id'], $id_estudiante, $altura, $peso_bruto, $motivo_descuento, $peso_descuento, $observaciones, $imc, $diagnostico]);
            
            $id_registro = $pdo->lastInsertId();
            $mensaje_extra = "";

            if (strpos($diagnostico, 'Bajo Peso') !== false || strpos($diagnostico, 'Obesidad') !== false) {
                $descripcion = "Estudiante diagnosticado con $diagnostico (IMC: $imc, Edad: $edad). Requiere seguimiento.";
                $pdo->prepare("INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES (?, ?, ?, 1)")->execute([$id_registro, "Riesgo de Malnutrición", $descripcion]);
                $id_alerta_generada = $pdo->lastInsertId();
                $resultado_mail = notificarRiesgoDAEM($pdo, $estudiante, $diagnostico, $imc, $peso_real, $altura, $id_alerta_generada);
                if ($resultado_mail === true) $mensaje_extra = " <br><i class='fa-solid fa-envelope-circle-check'></i> Notificación enviada al DAEM.";
            }
            $pdo->commit();
            $mensaje = "Medición registrada. Diagnóstico: <strong>$diagnostico</strong> (IMC: $imc)" . $mensaje_extra;
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Medición</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>.imc-preview { font-weight: bold; margin-top: 15px; padding: 10px; border-radius: 4px; background-color: #f8f9fa; border: 1px solid #e9ecef; text-align: center; } .hint { font-size: 0.85rem; color: #6c757d; display: block; margin-top: 4px; }</style>
</head>
<body>
    
    <header class="main-header">
        <div class="header-left">
            <a href="dashboard_profesor.php?vista=estudiantes&id_curso=<?php echo $estudiante['Id_Curso']; ?>" class="btn-header-back">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">
                Registro Clínico
            </div>
        </div>
        <div class="header-user-section">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></span>
                <span class="user-role">Docente</span>
            </div>
            <a href="logout.php" class="btn-logout" title="Cerrar Sesión"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 800px; margin: 0 auto;">
            
            <div style="border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
                <h1 style="margin:0;"><i class="fa-solid fa-weight-scale" style="color:var(--primary-color);"></i> Nueva Medición</h1>
                <p style="color:#666; margin-top:5px;">Estudiante: <strong><?php echo htmlspecialchars($estudiante['Nombres']." ".$estudiante['ApellidoPaterno']); ?></strong> (<?php echo $estudiante['Rut']; ?>)</p>
            </div>

            <?php if (!empty($errores)): ?>
                <div class="mensaje error"><ul><?php foreach ($errores as $err): ?><li><?php echo $err; ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php if ($tipo_mensaje == 'success'): ?>
                    <div style="margin-top:20px; text-align:center;">
                        <a href="dashboard_profesor.php?vista=mediciones&id_estudiante=<?php echo $id_estudiante; ?>" class="btn-create" style="background:#6c757d; text-decoration:none;">
                            <i class="fa-solid fa-list-check"></i> Ir al Historial
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tipo_mensaje !== 'success'): ?>
            <form method="POST" class="crud-form" id="formMedicion">
                <div style="display:flex; gap:20px; flex-wrap:wrap;">
                    <div class="form-group" style="flex:1;">
                        <label>Altura (Metros):</label>
                        <input type="number" step="0.01" min="0.50" max="2.10" id="altura" name="altura" required placeholder="Ej: 1.65" oninput="calcularIMC()" value="<?php echo isset($_POST['altura']) ? htmlspecialchars($_POST['altura']) : ''; ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Peso (Kg):</label>
                        <input type="number" step="0.01" min="1" max="200" id="peso" name="peso" required placeholder="Ej: 60.5" oninput="calcularIMC()" value="<?php echo isset($_POST['peso']) ? htmlspecialchars($_POST['peso']) : ''; ?>">
                    </div>
                </div>
                
                <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin-bottom:20px; border:1px dashed #ced4da;">
                    <h4 style="margin-bottom:10px; font-size:0.9rem; color:#666;">Descuento por Ropa (Opcional)</h4>
                    <div style="display:flex; gap:15px;">
                        <div class="form-group" style="flex:2;"><input type="text" name="motivo_descuento" placeholder="Ej: Zapatillas"></div>
                        <div class="form-group" style="flex:1;"><input type="number" step="0.01" id="peso_descuento" name="peso_descuento" value="0" oninput="calcularIMC()" placeholder="Kg"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Observaciones:</label>
                    <textarea name="observaciones" rows="3"></textarea>
                </div>

                <div id="resultadoIMC" class="imc-preview" style="display:none;"></div>

                <div class="form-actions" style="margin-top:20px;">
                    <button type="submit" class="btn-create" style="width:100%;"><i class="fa-solid fa-save"></i> Registrar</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <footer class="main-footer">
            &copy; <?php echo date("Y"); ?> NutriData.
        </footer>
    </main>

    <script>
        function calcularIMC() {
            const altura = parseFloat(document.getElementById('altura').value);
            const peso = parseFloat(document.getElementById('peso').value);
            const descuento = parseFloat(document.getElementById('peso_descuento').value) || 0;
            const div = document.getElementById('resultadoIMC');

            if (altura > 0 && peso > 0) {
                div.style.display = 'block';
                if (altura > 2.10) { div.innerHTML = "<span style='color:red'>Altura inválida</span>"; return; }
                const pesoReal = peso - descuento;
                const imc = pesoReal / (altura * altura);
                div.innerHTML = `IMC Estimado: <strong>${imc.toFixed(2)}</strong>`;
            } else { div.style.display = 'none'; }
        }
    </script>
</body>
</html>