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
$errores = [];
$mensaje = '';
$tipo_mensaje = '';

// 1. Verificar estudiante y obtener datos (INCLUYENDO Id_Curso PARA EL BOTÓN VOLVER)
if ($id_estudiante) {
    $stmt = $pdo->prepare("SELECT Nombre, Apellido, Rut, FechaNacimiento, Id_Curso FROM Estudiante WHERE Id = ?");
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) die("Estudiante no encontrado.");
} else {
    header("Location: dashboard_profesor.php");
    exit;
}

// --- FUNCIÓN DE DIAGNÓSTICO (Lógica de Negocio) ---
function calcularDiagnostico($imc, $edad) {
    if ($edad < 19) {
        // Criterio Escolar Simplificado
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
    // Recibir datos
    $peso_bruto = floatval($_POST['peso']);
    $altura = floatval($_POST['altura']);
    $motivo_descuento = trim($_POST['motivo_descuento']);
    $peso_descuento = floatval($_POST['peso_descuento']);
    $observaciones = trim($_POST['observaciones']);

    // 2. VALIDACIONES DE SEGURIDAD (Sanity Checks)
    // --- CORRECCIÓN: Límite estricto de 2.00m ---
    if ($altura < 0.50 || $altura > 2.00) {
        $errores[] = "La altura está fuera de rango (0.50m - 2.00m). Si usó centímetros (ej: 165), convierta a metros (ej: 1.65).";
    }
    if ($peso_bruto < 10 || $peso_bruto > 300) {
        $errores[] = "El peso debe estar entre 10kg y 300kg.";
    }
    
    $peso_real = $peso_bruto - $peso_descuento;
    if ($peso_real <= 0) {
        $errores[] = "El peso final no puede ser cero o negativo. Revise el descuento.";
    }

    // 3. GUARDAR SI NO HAY ERRORES
    if (empty($errores)) {
        try {
            // Cálculos
            $imc = round($peso_real / ($altura * $altura), 2);
            
            // Calcular Edad
            $fecha_nac = new DateTime($estudiante['FechaNacimiento']);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;

            // Obtener Diagnóstico
            $diagnostico = calcularDiagnostico($imc, $edad);

            $pdo->beginTransaction();

            // Insertar con Diagnóstico
            $sql = "INSERT INTO RegistroNutricional 
                    (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, Diagnostico, FechaMedicion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
            
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->execute([
                $_SESSION['user_id'], $id_estudiante, $altura, $peso_bruto, 
                $motivo_descuento, $peso_descuento, $observaciones, $imc, $diagnostico
            ]);
            
            $id_registro = $pdo->lastInsertId();

            // Generar Alerta
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
            $errores[] = "Error: " . $e->getMessage();
        }
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
    <style>
        .imc-preview { font-weight: bold; margin-top: 15px; padding: 10px; border-radius: 4px; background-color: #f8f9fa; border: 1px solid #e9ecef; text-align: center; }
        .hint { font-size: 0.85rem; color: #6c757d; display: block; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                <a href="dashboard_profesor.php?vista=estudiantes&id_curso=<?php echo $estudiante['Id_Curso']; ?>" class="nav-item active">
                    <i class="fa-solid fa-arrow-left"></i> Volver al listado
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div></header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-weight-scale"></i> Nueva Medición</h1>
                    <h3>Estudiante: <?php echo htmlspecialchars($estudiante['Nombre'] . " " . $estudiante['Apellido']); ?></h3>
                    <p style="color:#666;">RUT: <?php echo htmlspecialchars($estudiante['Rut']); ?></p>
                    <hr>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <strong>Atención:</strong>
                            <ul style="margin-top:5px; margin-left:20px;">
                                <?php foreach ($errores as $err): ?><li><?php echo $err; ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensaje): ?>
                        <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                        <?php if ($tipo_mensaje == 'success'): ?>
                            <div style="margin-top: 20px;">
                                <a href="dashboard_profesor.php?vista=mediciones&id_estudiante=<?php echo $id_estudiante; ?>" class="btn-create" style="background:#6c757d; text-decoration:none;">
                                    <i class="fa-solid fa-list-check"></i> Ver Historial
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($tipo_mensaje !== 'success'): ?>
                    <form method="POST" class="crud-form" id="formMedicion" style="margin-top: 20px;">
                        
                        <div style="display:flex; gap:20px; flex-wrap:wrap;">
                            <div class="form-group" style="flex:1; min-width:200px;">
                                <label>Altura (Metros):</label>
                                <input type="number" step="0.01" min="0.50" max="2.00" id="altura" name="altura" 
                                       required placeholder="Ej: 1.65" oninput="calcularIMC()"
                                       value="<?php echo isset($_POST['altura']) ? htmlspecialchars($_POST['altura']) : ''; ?>">
                                <span class="hint">Use punto. Mín: 0.50m, Máx: 2.00m</span>
                            </div>
                            <div class="form-group" style="flex:1; min-width:200px;">
                                <label>Peso (Kg):</label>
                                <input type="number" step="0.01" min="10" max="300" id="peso" name="peso" 
                                       required placeholder="Ej: 60.5" oninput="calcularIMC()"
                                       value="<?php echo isset($_POST['peso']) ? htmlspecialchars($_POST['peso']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin-bottom:20px; border: 1px dashed #ced4da;">
                            <h4 style="margin-bottom:10px; color:#495057;"><i class="fa-solid fa-shirt"></i> Descuento (Opcional)</h4>
                            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                                <div class="form-group" style="flex:2; min-width:200px;">
                                    <label>Motivo:</label>
                                    <input type="text" name="motivo_descuento" placeholder="Ej: Zapatillas">
                                </div>
                                <div class="form-group" style="flex:1; min-width:150px;">
                                    <label>Kg a descontar:</label>
                                    <input type="number" step="0.01" min="0" max="5" id="peso_descuento" name="peso_descuento" value="0" oninput="calcularIMC()">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Observaciones:</label>
                            <textarea name="observaciones" rows="3"></textarea>
                        </div>

                        <div id="resultadoIMC" class="imc-preview" style="display:none;"></div>

                        <div class="form-actions" style="margin-top:20px;">
                            <button type="submit" class="btn-create" style="width:100%; padding:12px; font-size:1.1rem;">Guardar Medición</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        function calcularIMC() {
            const altura = parseFloat(document.getElementById('altura').value);
            const peso = parseFloat(document.getElementById('peso').value);
            const descuento = parseFloat(document.getElementById('peso_descuento').value) || 0;
            const divResultado = document.getElementById('resultadoIMC');

            if (altura > 0 && peso > 0) {
                divResultado.style.display = 'block';
                // CORRECCIÓN: Validación visual en JS
                if (altura > 2.00) {
                    divResultado.innerHTML = "<span style='color:#dc3545;'><i class='fa-solid fa-circle-xmark'></i> Altura fuera de rango (Máx 2.00m). ¿Usó centímetros?</span>";
                    return;
                }
                const pesoReal = peso - descuento;
                const imc = pesoReal / (altura * altura);
                
                // Feedback visual simple
                let color = "#198754";
                if(imc < 16.5 || imc > 25) color = "#fd7e14"; 

                divResultado.innerHTML = `Peso Real: <strong>${pesoReal.toFixed(2)} kg</strong> | IMC Estimado: <strong style="color:${color}">${imc.toFixed(2)}</strong>`;
            } else {
                divResultado.style.display = 'none';
            }
        }
    </script>
</body>
</html>