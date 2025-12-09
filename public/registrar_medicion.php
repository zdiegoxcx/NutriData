<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
require_once __DIR__ . '/../src/enviar_alerta.php'; // Tu sistema de correos

$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'profesor') {
    header("Location: login.php");
    exit;
}

$id_estudiante = $_GET['id_estudiante'] ?? null;
$errores = [];
$mensaje = '';
$tipo_mensaje = '';

// 1. OBTENER DATOS COMPLETOS (Estudiante + Curso + Colegio)
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
    header("Location: dashboard_profesor.php");
    exit;
}

// 2. LÓGICA DE DIAGNÓSTICO REFINADA (MINSAL CHILE / OMS)
function calcularDiagnostico($imc, $edad) {
    // Adultos (19 años o más) - Clasificación Completa OMS
    if ($edad >= 19) {
        if ($imc < 18.5) return 'Bajo Peso';
        if ($imc >= 18.5 && $imc < 25) return 'Normal';
        if ($imc >= 25.0 && $imc < 30) return 'Sobrepeso';
        if ($imc >= 30.0 && $imc < 35) return 'Obesidad Grado I';
        if ($imc >= 35.0 && $imc < 40) return 'Obesidad Grado II';
        if ($imc >= 40.0) return 'Obesidad Grado III';
    } 
    // Niños y Adolescentes (Aproximación Estándar Chile)
    // Nota: En clínica se usan Desviaciones Estándar (DE). Aquí usamos rangos aproximados.
    else {
        // Básica (aprox 6-13 años)
        if ($edad <= 13) {
            if ($imc < 14.5) return 'Bajo Peso';
            if ($imc >= 14.5 && $imc < 20) return 'Normal'; 
            if ($imc >= 20 && $imc < 23) return 'Sobrepeso';
            if ($imc >= 23 && $imc < 26) return 'Obesidad'; // Equivalente moderado
            if ($imc >= 26) return 'Obesidad Severa';       // Equivalente a Grado II/III
        }
        // Media (14-18 años)
        else {
            if ($imc < 17.5) return 'Bajo Peso';
            if ($imc >= 17.5 && $imc < 24) return 'Normal';
            if ($imc >= 24 && $imc < 28) return 'Sobrepeso';
            if ($imc >= 28 && $imc < 32) return 'Obesidad'; 
            if ($imc >= 32) return 'Obesidad Severa';
        }
    }
    return 'Normal'; 
}

// 3. PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peso_bruto = floatval($_POST['peso']);
    $altura = floatval($_POST['altura']);
    $motivo_descuento = trim($_POST['motivo_descuento']);
    $peso_descuento = floatval($_POST['peso_descuento']);
    $observaciones = trim($_POST['observaciones']);

    // Validaciones
    if ($altura < 0.50 || $altura > 2.10) $errores[] = "La altura debe estar entre 0.50m y 2.10m.";
    if ($peso_bruto <= 0 || $peso_bruto > 200) $errores[] = "El peso debe ser mayor a 0 y máximo 200kg.";
    if (!is_numeric($_POST['peso_descuento']) || $_POST['peso_descuento'] < 0) $errores[] = "El descuento debe ser positivo.";

    $peso_real = $peso_bruto - $peso_descuento;
    if ($peso_real <= 0) $errores[] = "El peso final no puede ser cero o negativo.";

    if (empty($errores)) {
        try {
            $imc = round($peso_real / ($altura * $altura), 2);
            
            // Calcular edad
            $fecha_nac = new DateTime($estudiante['FechaNacimiento']);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;

            $diagnostico = calcularDiagnostico($imc, $edad);

            $pdo->beginTransaction();

            // Insertar Medición
            $sql = "INSERT INTO RegistroNutricional 
                    (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, Diagnostico, FechaMedicion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
            
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->execute([
                $_SESSION['user_id'], $id_estudiante, $altura, $peso_bruto, 
                $motivo_descuento, $peso_descuento, $observaciones, $imc, $diagnostico
            ]);
            
            $id_registro = $pdo->lastInsertId();
            $mensaje_extra = "";

            // --- GESTIÓN DE ALERTAS (Ahora incluye todos los grados de obesidad) ---
            // Detectamos cualquier palabra "Obesidad" o "Bajo Peso" en el diagnóstico
            if (strpos($diagnostico, 'Bajo Peso') !== false || strpos($diagnostico, 'Obesidad') !== false) {
                
                // 1. Guardar Alerta
                $descripcion = "Estudiante diagnosticado con $diagnostico (IMC: $imc, Edad: $edad). Requiere seguimiento.";
                $sql_alerta = "INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) VALUES (?, ?, ?, 1)";
                $pdo->prepare($sql_alerta)->execute([$id_registro, "Riesgo de Malnutrición", $descripcion]);
                
                $id_alerta_generada = $pdo->lastInsertId(); 

                // 2. Enviar Correo
                $resultado_mail = notificarRiesgoDAEM($pdo, $estudiante, $diagnostico, $imc, $peso_real, $altura, $id_alerta_generada);

                if ($resultado_mail === true) {
                    $mensaje_extra = " <br><i class='fa-solid fa-envelope-circle-check' style='color:#198754'></i> Notificación enviada al DAEM.";
                } else {
                    $mensaje_extra = " <br><span style='color:#fd7e14'><i class='fa-solid fa-circle-exclamation'></i> Alerta guardada, pero falló el correo ($resultado_mail)</span>";
                }
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
                    <h3>Estudiante: <?php echo htmlspecialchars($estudiante['Nombres'] . " " . $estudiante['ApellidoPaterno'] . " " . $estudiante['ApellidoMaterno']); ?></h3>
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
                                <input type="number" step="0.01" min="0.50" max="2.10" id="altura" name="altura" 
                                       required placeholder="Ej: 1.65" oninput="calcularIMC()"
                                       value="<?php echo isset($_POST['altura']) ? htmlspecialchars($_POST['altura']) : ''; ?>">
                                <span class="hint">Use punto. Mín: 0.50m, Máx: 2.10m</span>
                            </div>
                            <div class="form-group" style="flex:1; min-width:200px;">
                                <label>Peso (Kg):</label>
                                <input type="number" step="0.01" min="1" max="200" id="peso" name="peso" 
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
            <footer class="main-footer">
                &copy; <?php echo date("Y"); ?> <strong>NutriData</strong> - Departamento de Administración de Educación Municipal (DAEM).
            </footer>
        </main>
    </div>

   <script>
        // 1. Lógica para calcular IMC en tiempo real (La que ya tenías)
        function calcularIMC() {
            const altura = parseFloat(document.getElementById('altura').value);
            const peso = parseFloat(document.getElementById('peso').value);
            const descuento = parseFloat(document.getElementById('peso_descuento').value) || 0;
            const divResultado = document.getElementById('resultadoIMC');

            if (altura > 0 && peso > 0) {
                divResultado.style.display = 'block';
                
                // Validación visual
                if (altura > 2.10) {
                    divResultado.innerHTML = "<span style='color:#dc3545;'><i class='fa-solid fa-circle-xmark'></i> Altura fuera de rango (Máx 2.10m).</span>";
                    return;
                }
                const pesoReal = peso - descuento;
                const imc = pesoReal / (altura * altura);
                
                let color = "#198754"; // Verde
                // Lógica visual simple para el color (puedes ajustarla)
                if(imc < 18.5 || imc > 25) color = "#fd7e14"; // Naranja
                if(imc > 30) color = "#dc3545"; // Rojo

                divResultado.innerHTML = `Peso Real: <strong>${pesoReal.toFixed(2)} kg</strong> | IMC Estimado: <strong style="color:${color}">${imc.toFixed(2)}</strong>`;
            } else {
                divResultado.style.display = 'none';
            }
        }

        // 2. NUEVA LÓGICA: Bloquear botón al enviar
        document.getElementById('formMedicion').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            
            // Si el formulario es válido (el navegador ya chequeó los 'required'), procedemos
            if (this.checkValidity()) {
                // Cambiamos el texto del botón y lo desactivamos
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando y Enviando...';
                btn.style.opacity = '0.7';
                btn.style.cursor = 'wait';
                
                // Pequeño truco: Desactivamos el botón justo después de que empiece el envío
                // para asegurar que el dato POST del submit viaje (aunque en este caso no es vital porque no usas el name del botón)
                setTimeout(() => {
                    btn.disabled = true;
                }, 10);
            }
        });
    </script>
</body>
</html>
</body>
</html>