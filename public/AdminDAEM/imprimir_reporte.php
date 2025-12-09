<?php
session_start();
require_once __DIR__ . '/../../src/config/db.php';
$pdo = getConnection();

// --- SEGURIDAD ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    die("Acceso denegado");
}

// 1. Recibir Filtros
$rep_colegio = $_GET['rep_colegio'] ?? '';
$rep_curso   = $_GET['rep_curso'] ?? '';
$rep_sexo    = $_GET['rep_sexo'] ?? '';
$fecha_ini   = $_GET['fecha_ini'] ?? '2000-01-01';
$fecha_fin   = $_GET['fecha_fin'] ?? date('Y-m-d');

// 2. Construir Query
$cond_rep = ["r.FechaMedicion BETWEEN ? AND ?"];
$params_rep = [$fecha_ini, $fecha_fin];

if ($rep_colegio) { 
    $cond_rep[] = "c.Id_Establecimiento = ?"; 
    $params_rep[] = $rep_colegio; 
}

// --- FILTRO INTELIGENTE DE CURSO ---
if ($rep_curso) { 
    if (is_numeric($rep_curso)) {
        // ID específico (Colegio seleccionado)
        $cond_rep[] = "c.Id = ?"; 
        $params_rep[] = $rep_curso; 
    } else {
        // Nombre de Nivel (Todos los colegios) -> LIKE '1° Básico%'
        $cond_rep[] = "c.Nombre LIKE ?"; 
        $params_rep[] = $rep_curso . "%"; 
    }
}
// -----------------------------------

if ($rep_sexo) { $cond_rep[] = "e.Sexo = ?"; $params_rep[] = $rep_sexo; }

$where_rep = "WHERE " . implode(" AND ", $cond_rep);

// 3. Consulta SQL con LÍMITE DE SEGURIDAD (500)
$sql = "
    SELECT 
        e.Rut, 
        CONCAT_WS(' ', e.Nombres, e.ApellidoPaterno, e.ApellidoMaterno) as Estudiante,
        e.Sexo,
        TIMESTAMPDIFF(YEAR, e.FechaNacimiento, CURDATE()) as Edad,
        c.Nombre as Curso,
        est.Nombre as Colegio,
        r.FechaMedicion,
        r.Peso, r.Altura, r.IMC, r.Diagnostico
    FROM RegistroNutricional r
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
    $where_rep
    ORDER BY est.Nombre, c.Nombre, e.ApellidoPaterno
    LIMIT 500 
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params_rep);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_mostrados = count($resultados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Nutricional</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4361ee; padding-bottom: 10px; }
        .logo { font-size: 20px; font-weight: bold; color: #4361ee; text-transform: uppercase; }
        .info { font-size: 10px; color: #666; margin-top: 5px; }
        .alerta-limite { border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; text-align: center; border-radius: 4px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { background-color: #e9ecef; color: #111; font-weight: bold; padding: 6px; border: 1px solid #ccc; text-align: left; }
        td { padding: 5px; border: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .text-danger { color: #dc3545; font-weight: bold; }
        .text-warning { color: #ffc107; font-weight: bold; text-shadow: 0px 0px 1px #997404; }
        .text-success { color: #198754; font-weight: bold; }
        @media print { .no-print { display: none; } @page { size: landscape; margin: 1cm; } body { -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body onload="window.print()">
    
    <div class="header">
        <div class="logo">NutriData - Reporte Oficial</div>
        <div class="info">
            Generado el: <?php echo date("d/m/Y H:i"); ?> <br>
            Rango: <?php echo date("d/m/Y", strtotime($fecha_ini)); ?> al <?php echo date("d/m/Y", strtotime($fecha_fin)); ?>
        </div>
    </div>

    <?php if ($total_mostrados >= 500): ?>
        <div class="alerta-limite">
            <strong>⚠️ ATENCIÓN: REPORTE CORTADO</strong><br>
            Por seguridad, este PDF solo muestra los primeros <strong>500 registros</strong>.<br>
            Para el listado completo, use la opción <strong>"Exportar Excel"</strong>.
        </div>
    <?php else: ?>
        <div style="text-align:center; font-size:11px; margin-bottom:10px; color:#666;">
            Total de registros: <strong><?php echo $total_mostrados; ?></strong>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>RUT</th><th>Estudiante</th><th style="text-align:center">Sexo</th><th style="text-align:center">Edad</th>
                <th>Curso</th><th>Colegio</th><th>Fecha</th><th>IMC</th><th>Diagnóstico</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($resultados as $row): ?>
            <tr>
                <td><?php echo $row['Rut']; ?></td>
                <td><?php echo htmlspecialchars($row['Estudiante']); ?></td>
                <td style="text-align:center"><?php echo $row['Sexo']; ?></td>
                <td style="text-align:center"><?php echo $row['Edad']; ?></td>
                <td><?php echo htmlspecialchars($row['Curso']); ?></td>
                <td><?php echo htmlspecialchars($row['Colegio']); ?></td>
                <td><?php echo date("d/m/Y", strtotime($row['FechaMedicion'])); ?></td>
                <td><strong><?php echo $row['IMC']; ?></strong></td>
                <td>
                    <?php 
                    $clase = 'text-dark';
                    if (strpos($row['Diagnostico'], 'Obesidad') !== false) $clase = 'text-danger';
                    elseif (strpos($row['Diagnostico'], 'Bajo') !== false) $clase = 'text-warning';
                    elseif (strpos($row['Diagnostico'], 'Normal') !== false) $clase = 'text-success';
                    echo "<span class='$clase'>{$row['Diagnostico']}</span>";
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background:#4361ee; color:white; border:none; border-radius:4px;">🖨️ Imprimir</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background:#6c757d; color:white; border:none; border-radius:4px; margin-left:10px;">Cerrar Pestaña</button>
    </div>
</body>
</html>