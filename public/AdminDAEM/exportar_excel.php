<?php
session_start();
require_once __DIR__ . '/../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    die("Acceso denegado");
}

// Recibir Filtros
$rep_colegio = $_GET['rep_colegio'] ?? '';
$rep_curso = $_GET['rep_curso'] ?? '';
$rep_sexo = $_GET['rep_sexo'] ?? '';
$fecha_ini = $_GET['fecha_ini'] ?? '2000-01-01';
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Construir Query
$cond_rep = ["r.FechaMedicion BETWEEN ? AND ?"];
$params_rep = [$fecha_ini, $fecha_fin];

if ($rep_colegio) { 
    $cond_rep[] = "c.Id_Establecimiento = ?"; 
    $params_rep[] = $rep_colegio; 
}

// --- FILTRO INTELIGENTE DE CURSO ---
if ($rep_curso) { 
    if (is_numeric($rep_curso)) {
        $cond_rep[] = "c.Id = ?"; 
        $params_rep[] = $rep_curso; 
    } else {
        $cond_rep[] = "c.Nombre LIKE ?"; 
        $params_rep[] = $rep_curso . "%"; 
    }
}
// -----------------------------------

if ($rep_sexo) { $cond_rep[] = "e.Sexo = ?"; $params_rep[] = $rep_sexo; }

$where_rep = "WHERE " . implode(" AND ", $cond_rep);

$sql = "
    SELECT 
        e.Rut, 
        CONCAT_WS(' ', e.Nombres, e.ApellidoPaterno, e.ApellidoMaterno) as Estudiante,
        e.Sexo,
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
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params_rep);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configurar Headers para descarga Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Reporte_Nutricional_" . date('Ymd') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Generar Tabla HTML (Excel la interpreta)
echo "<table border='1'>";
echo "<tr>
        <th style='background-color:#4361ee; color:white;'>RUT</th>
        <th style='background-color:#4361ee; color:white;'>Estudiante</th>
        <th style='background-color:#4361ee; color:white;'>Sexo</th>
        <th style='background-color:#4361ee; color:white;'>Curso</th>
        <th style='background-color:#4361ee; color:white;'>Colegio</th>
        <th style='background-color:#4361ee; color:white;'>Fecha</th>
        <th style='background-color:#4361ee; color:white;'>Peso</th>
        <th style='background-color:#4361ee; color:white;'>Altura</th>
        <th style='background-color:#4361ee; color:white;'>IMC</th>
        <th style='background-color:#4361ee; color:white;'>Diagnóstico</th>
      </tr>";

foreach ($filas as $f) {
    $color = '#000000';
    if (strpos($f['Diagnostico'], 'Obesidad') !== false) $color = '#dc3545';
    elseif (strpos($f['Diagnostico'], 'Bajo') !== false) $color = '#ffc107';
    elseif (strpos($f['Diagnostico'], 'Sobrepeso') !== false) $color = '#fd7e14';
    elseif (strpos($f['Diagnostico'], 'Normal') !== false) $color = '#198754';

    echo "<tr>
            <td>{$f['Rut']}</td>
            <td>" . mb_convert_encoding($f['Estudiante'], 'UTF-16LE', 'UTF-8') . "</td> <td>{$f['Sexo']}</td>
            <td>" . mb_convert_encoding($f['Curso'], 'UTF-16LE', 'UTF-8') . "</td>
            <td>" . mb_convert_encoding($f['Colegio'], 'UTF-16LE', 'UTF-8') . "</td>
            <td>{$f['FechaMedicion']}</td>
            <td>{$f['Peso']}</td>
            <td>{$f['Altura']}</td>
            <td>{$f['IMC']}</td>
            <td style='color:$color; font-weight:bold;'>{$f['Diagnostico']}</td>
          </tr>";
}
echo "</table>";
?>