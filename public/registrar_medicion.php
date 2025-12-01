<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN: SOLO PROFESORES ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'profesor') {
    header("Location: login.php");
    exit;
}

$id_estudiante = $_GET['id_estudiante'] ?? null;
$mensaje = '';
$tipo_mensaje = '';

// Verificar que el estudiante existe y obtener su Fecha de Nacimiento
if ($id_estudiante) {
    $stmt = $pdo->prepare("SELECT Nombre, Apellido, Rut, FechaNacimiento FROM Estudiante WHERE Id = ?");
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) die("Estudiante no encontrado.");
} else {
    header("Location: dashboard_profesor.php");
    exit;
}

// --- FUNCIÓN DE DIAGNÓSTICO (Lógica de Negocio) ---
function calcularDiagnostico($imc, $edad) {
    // AQUÍ PUEDES MEJORAR LA LÓGICA EN EL FUTURO (TABLAS OMS)
    // Por ahora, usamos un criterio simplificado pero adaptado:
    
    if ($edad < 19) {
        // Criterio Escolar Simplificado (Ejemplo referencial)
        if ($imc < 16.5) return 'Bajo Peso';
        if ($imc >= 16.5 && $imc < 23) return 'Normal';
        if ($imc >= 23 && $imc < 27) return 'Sobrepeso';
        if ($imc >= 27) return 'Obesidad';
    } else {
        // Criterio Adulto Estándar
        if ($imc < 18.5) return 'Bajo Peso';
        if ($imc >= 18.5 && $imc < 25) return 'Normal';
        if ($imc >= 25 && $imc < 30) return 'Sobrepeso';
        if ($imc >= 30) return 'Obesidad';
    }
    return 'Normal';
}

// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peso_bruto = floatval($_POST['peso']);
    $altura = floatval($_POST['altura']);
    $motivo_descuento = trim($_POST['motivo_descuento']);
    $peso_descuento = floatval($_POST['peso_descuento']);
    $observaciones = trim($_POST['observaciones']);

    if ($peso_bruto > 0 && $altura > 0) {
        $peso_real = $peso_bruto - $peso_descuento;
        
        if ($peso_real <= 0) {
            $mensaje = "El peso con descuento no puede ser cero o negativo.";
            $tipo_mensaje = "error";
        } else {
            // 1. Cálculos
            $imc = round($peso_real / ($altura * $altura), 2);
            
            // Calcular edad exacta
            $fecha_nac = new DateTime($estudiante['FechaNacimiento']);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;

            // Obtener diagnóstico textual
            $diagnostico = calcularDiagnostico($imc, $edad);

            try {
                $pdo->beginTransaction();

                // 2. Insertar en RegistroNutricional (AHORA CON DIAGNÓSTICO)
                $sql = "INSERT INTO RegistroNutricional 
                        (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, Diagnostico, FechaMedicion) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
                
                $stmt_insert = $pdo->prepare($sql);
                $stmt_insert->execute([
                    $_SESSION['user_id'], $id_estudiante, $altura, $peso_bruto, 
                    $motivo_descuento, $peso_descuento, $observaciones, $imc, $diagnostico
                ]);
                
                $id_registro = $pdo->lastInsertId();

                // 3. GENERAR ALERTA SI ES NECESARIO
                // Ahora nos basamos en el diagnóstico, no en el número crudo
                if ($diagnostico == 'Bajo Peso' || $diagnostico == 'Obesidad') {
                    $descripcion = "Estudiante diagnosticado con $diagnostico (IMC: $imc, Edad: $edad). Requiere seguimiento.";
                    $sql_alerta = "INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES (?, ?, ?, 1)";
                    $pdo->prepare($sql_alerta)->execute([$id_registro, "Riesgo de Malnutrición", $descripcion]);
                }

                $pdo->commit();
                $mensaje = "Medición registrada. Diagnóstico: <strong>$diagnostico</strong> (IMC: $imc)";
                $tipo_mensaje = "success";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensaje = "Error: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } else {
        $mensaje = "Datos inválidos."; $tipo_mensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Medición</title>
    <link rel="stylesheet" href="css/styles.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>.imc-preview { font-weight: bold; margin-top: 10px; color: #0d6efd; }</style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                <a href="dashboard_profesor.php?vista=estudiantes" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div></header>
            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-weight-scale"></i> Nueva Medición</h1>
                    <h3>Estudiante: <?php echo htmlspecialchars($estudiante['Nombre'] . " " . $estudiante['Apellido']); ?></h3>
                    
                    <?php if ($mensaje): ?>
                        <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                    <?php endif; ?>

                    <?php if ($tipo_mensaje !== 'success'): ?>
                    <form method="POST" class="crud-form">
                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Altura (m):</label>
                                <input type="number" step="0.01" id="altura" name="altura" required placeholder="1.65">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Peso (kg):</label>
                                <input type="number" step="0.01" id="peso" name="peso" required placeholder="60.5">
                            </div>
                        </div>
                        
                        <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin-bottom:20px;">
                            <h4><i class="fa-solid fa-shirt"></i> Descuento (Opcional)</h4>
                            <div style="display:flex; gap:20px;">
                                <div class="form-group" style="flex:2;"><label>Motivo:</label><input type="text" name="motivo_descuento"></div>
                                <div class="form-group" style="flex:1;"><label>Kilos a descontar:</label><input type="number" step="0.01" name="peso_descuento" value="0"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Observaciones:</label>
                            <textarea name="observaciones" rows="3"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-create" style="width:100%;">Guardar Medición</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>