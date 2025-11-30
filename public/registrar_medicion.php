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

// Verificar que el estudiante existe
if ($id_estudiante) {
    $stmt = $pdo->prepare("SELECT Nombre, Apellido, Rut FROM Estudiante WHERE Id = ?");
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) {
        die("Estudiante no encontrado.");
    }
} else {
    header("Location: dashboard_profesor.php");
    exit;
}

// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peso_bruto = floatval($_POST['peso']);
    $altura = floatval($_POST['altura']);
    $motivo_descuento = trim($_POST['motivo_descuento']);
    $peso_descuento = floatval($_POST['peso_descuento']);
    $observaciones = trim($_POST['observaciones']);

    if ($peso_bruto > 0 && $altura > 0) {
        
        // 1. Calcular Peso Real y IMC
        // Si hay descuento (ropa, yeso), se resta del peso bruto
        $peso_real = $peso_bruto - $peso_descuento;
        
        // Evitar división por cero o pesos negativos
        if ($peso_real <= 0) {
            $mensaje = "El peso con descuento no puede ser cero o negativo.";
            $tipo_mensaje = "error";
        } else {
            // Fórmula IMC: Peso (kg) / (Altura (m) * Altura (m))
            $imc = $peso_real / ($altura * $altura);
            $imc = round($imc, 2); // Redondear a 2 decimales

            try {
                $pdo->beginTransaction();

                // 2. Insertar en RegistroNutricional
                // Nota: Guardamos el Peso bruto en 'Peso' y el descuento en 'PesoDescuento' según tu SQL
                $sql = "INSERT INTO RegistroNutricional 
                        (Id_Profesor, Id_Estudiante, Altura, Peso, MotivoDescuento, PesoDescuento, Observaciones, IMC, FechaMedicion) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
                
                $stmt_insert = $pdo->prepare($sql);
                $stmt_insert->execute([
                    $_SESSION['user_id'],
                    $id_estudiante,
                    $altura,
                    $peso_bruto,
                    $motivo_descuento,
                    $peso_descuento,
                    $observaciones,
                    $imc
                ]);
                
                $id_registro = $pdo->lastInsertId();

                // 3. GENERACIÓN AUTOMÁTICA DE ALERTA
                // Si IMC < 18.5 (Bajo Peso) o IMC >= 25 (Sobrepeso/Obesidad)
                if ($imc < 18.5 || $imc >= 25) {
                    $estado_nutricional = ($imc < 18.5) ? "Bajo Peso" : "Exceso de Peso";
                    $descripcion_alerta = "Estudiante detectado con $estado_nutricional (IMC: $imc). Requiere seguimiento.";
                    
                    $sql_alerta = "INSERT INTO Alerta (Id_RegistroNutricional, Nombre, Descripcion, Estado) 
                                   VALUES (?, ?, ?, 1)"; // Estado 1 = Pendiente/Activa
                    $stmt_alerta = $pdo->prepare($sql_alerta);
                    $stmt_alerta->execute([$id_registro, "Riesgo de Malnutrición", $descripcion_alerta]);
                }

                $pdo->commit();
                $mensaje = "Medición registrada exitosamente. IMC calculado: " . $imc;
                $tipo_mensaje = "success";
                
                // Redirigir después de un momento o mostrar botón de volver
                // header("Refresh: 2; url=dashboard_profesor.php?vista=mediciones&id_estudiante=$id_estudiante");

            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensaje = "Error al guardar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } else {
        $mensaje = "El peso y la altura deben ser mayores a cero.";
        $tipo_mensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Medición</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .mensaje.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; padding: 15px; margin-bottom: 20px; border-radius: 4px;}
        .imc-preview { font-size: 1.2rem; font-weight: bold; margin-top: 10px; color: #0d6efd; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>NutriMonitor</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_profesor.php?vista=estudiantes&id_curso=<?php echo $_GET['id_curso'] ?? ''; // Intentar mantener contexto ?>" class="nav-item active">
                    <i class="fa-solid fa-arrow-left"></i> Volver al listado
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-weight-scale"></i> Nueva Medición</h1>
                    <h3>Estudiante: <?php echo htmlspecialchars($estudiante['Nombre'] . " " . $estudiante['Apellido']); ?></h3>
                    <p>RUT: <?php echo htmlspecialchars($estudiante['Rut']); ?></p>
                    <hr>

                    <?php if ($mensaje): ?>
                        <div class="mensaje <?php echo $tipo_mensaje; ?>">
                            <?php echo $mensaje; ?>
                            <?php if ($tipo_mensaje == 'success'): ?>
                                <br><a href="dashboard_profesor.php?vista=mediciones&id_estudiante=<?php echo $id_estudiante; ?>">Ver historial</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tipo_mensaje !== 'success'): // Ocultar formulario si ya se guardó ?>
                    <form method="POST" class="crud-form" id="formMedicion">
                        
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="altura">Altura (en Metros, ej: 1.65):</label>
                                <input type="number" step="0.01" id="altura" name="altura" required placeholder="1.65" oninput="calcularIMC()">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="peso">Peso (en KG, ej: 60.5):</label>
                                <input type="number" step="0.01" id="peso" name="peso" required placeholder="60.5" oninput="calcularIMC()">
                            </div>
                        </div>

                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                            <h4><i class="fa-solid fa-shirt"></i> Descuento de Peso (Opcional)</h4>
                            <div style="display: flex; gap: 20px;">
                                <div class="form-group" style="flex: 2;">
                                    <label for="motivo_descuento">Motivo (Ej: Ropa, Yeso):</label>
                                    <input type="text" id="motivo_descuento" name="motivo_descuento" placeholder="Ropa de invierno">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="peso_descuento">Kilos a descontar:</label>
                                    <input type="number" step="0.01" id="peso_descuento" name="peso_descuento" value="0" oninput="calcularIMC()">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="observaciones">Observaciones:</label>
                            <textarea id="observaciones" name="observaciones" rows="3" style="width:100%; padding:10px;"></textarea>
                        </div>

                        <div id="resultadoIMC" class="imc-preview"></div>

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn-create" style="width: 100%; padding: 12px; font-size: 1.1rem;">
                                <i class="fa-solid fa-save"></i> Guardar Medición
                            </button>
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
                const pesoReal = peso - descuento;
                if (pesoReal <= 0) {
                    divResultado.innerHTML = "<span style='color:red'>El peso real no puede ser cero o negativo.</span>";
                    return;
                }
                const imc = pesoReal / (altura * altura);
                let estado = "";
                let color = "";

                if (imc < 18.5) { estado = "Bajo Peso"; color = "#ffc107"; }
                else if (imc < 25) { estado = "Normal"; color = "#198754"; }
                else if (imc < 30) { estado = "Sobrepeso"; color = "#fd7e14"; }
                else { estado = "Obesidad"; color = "#dc3545"; }

                divResultado.innerHTML = `IMC Estimado: <strong>${imc.toFixed(2)}</strong> - <span style="color:${color}; font-weight:bold;">${estado}</span>`;
            } else {
                divResultado.innerHTML = "";
            }
        }
    </script>
</body>
</html>