<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: login.php"); exit;
}

// --- CONFIGURACIÓN GLOBAL ---
$vista = $_GET['vista'] ?? 'general'; // 'general' o 'reportes'
$registros_por_pagina = 20; 

// Listas para filtros
$colegios = $pdo->query("SELECT Id, Nombre FROM Establecimiento ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
$todos_los_cursos = $pdo->query("SELECT Id, Nombre, Id_Establecimiento FROM Curso ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

// Helper para mantener parámetros en la URL
function buildUrl($params = []) {
    $currentParams = $_GET;
    $merged = array_merge($currentParams, $params);
    return '?' . http_build_query($merged);
}

// =================================================================================
// LÓGICA VISTA GENERAL (DASHBOARD)
// =================================================================================
if ($vista === 'general') {
    $pag_general = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
    if ($pag_general < 1) $pag_general = 1;
    $offset_gen = ($pag_general - 1) * $registros_por_pagina;

    // Filtros
    $filtro_estado = isset($_GET['ver']) && $_GET['ver'] === 'todos' ? null : 1;
    $filtro_colegio = $_GET['colegio'] ?? null;
    $filtro_sexo = $_GET['sexo'] ?? null;

    // WHERE Dinámico
    $cond = []; $params = [];
    if ($filtro_colegio) { $cond[] = "c.Id_Establecimiento = ?"; $params[] = $filtro_colegio; }
    if ($filtro_sexo) { $cond[] = "e.Sexo = ?"; $params[] = $filtro_sexo; }
    $where_general = !empty($cond) ? " WHERE " . implode(" AND ", $cond) : "";

    // KPI
    $sql_kpi = "SELECT COUNT(*) as total, AVG(r.IMC) as prom_imc, SUM(CASE WHEN r.Diagnostico IN ('Bajo Peso', 'Obesidad', 'Obesidad Severa') THEN 1 ELSE 0 END) as riesgo
                FROM RegistroNutricional r JOIN Estudiante e ON r.Id_Estudiante = e.Id JOIN Curso c ON e.Id_Curso = c.Id
                INNER JOIN (SELECT Id_Estudiante, MAX(FechaMedicion) as MaxF FROM RegistroNutricional GROUP BY Id_Estudiante) u 
                ON r.Id_Estudiante = u.Id_Estudiante AND r.FechaMedicion = u.MaxF $where_general";
    $stmt_kpi = $pdo->prepare($sql_kpi); $stmt_kpi->execute($params); $kpis = $stmt_kpi->fetch(PDO::FETCH_ASSOC);
    $porcentaje = ($kpis['total'] > 0) ? round(($kpis['riesgo'] / $kpis['total']) * 100, 1) : 0;

    // Datos Gráficos
    $sql_graf = "SELECT r.Diagnostico as estado, COUNT(*) as cantidad FROM RegistroNutricional r
                 JOIN Estudiante e ON r.Id_Estudiante = e.Id JOIN Curso c ON e.Id_Curso = c.Id
                 INNER JOIN (SELECT Id_Estudiante, MAX(FechaMedicion) as MaxF FROM RegistroNutricional GROUP BY Id_Estudiante) u 
                 ON r.Id_Estudiante = u.Id_Estudiante AND r.FechaMedicion = u.MaxF $where_general GROUP BY r.Diagnostico";
    $stmt_graf = $pdo->prepare($sql_graf); $stmt_graf->execute($params); $datos_graf = $stmt_graf->fetchAll(PDO::FETCH_ASSOC);

    $labels = []; $data = []; $colores = [];
    foreach ($datos_graf as $fila) {
        $labels[] = $fila['estado']; $data[] = $fila['cantidad'];
        if(strpos($fila['estado'], 'Bajo') !== false) $colores[]='#ffc107';
        elseif(strpos($fila['estado'], 'Normal') !== false) $colores[]='#198754';
        elseif(strpos($fila['estado'], 'Sobrepeso') !== false) $colores[]='#fd7e14';
        else $colores[]='#dc3545';
    }

    // Tabla Alertas
    $sql_base = "FROM Alerta a JOIN RegistroNutricional r ON a.Id_RegistroNutricional = r.Id JOIN Estudiante e ON r.Id_Estudiante = e.Id JOIN Curso c ON e.Id_Curso = c.Id JOIN Establecimiento est ON c.Id_Establecimiento = est.Id";
    
    $cond_a = []; $params_a = [];
    if ($filtro_estado !== null) { $cond_a[] = "a.Estado = ?"; $params_a[] = $filtro_estado; }
    if ($filtro_colegio) { $cond_a[] = "c.Id_Establecimiento = ?"; $params_a[] = $filtro_colegio; }
    if ($filtro_sexo) { $cond_a[] = "e.Sexo = ?"; $params_a[] = $filtro_sexo; }
    $where_a = !empty($cond_a) ? " WHERE " . implode(" AND ", $cond_a) : "";

    $sql_conteo = "SELECT COUNT(*) $sql_base $where_a";
    $stmt_c = $pdo->prepare($sql_conteo); $stmt_c->execute($params_a);
    $total_regs_alertas = $stmt_c->fetchColumn();
    $total_pags_alertas = ceil($total_regs_alertas / $registros_por_pagina);

    $sql_alertas = "SELECT a.Id as IdAlerta, a.Estado, CONCAT_WS(' ', e.Nombres, e.ApellidoPaterno, e.ApellidoMaterno) as Estudiante, e.Rut, est.Nombre as Establecimiento, r.IMC, r.FechaMedicion, r.Diagnostico 
                    $sql_base WHERE a.Id IN (SELECT MAX(a2.Id) FROM Alerta a2 JOIN RegistroNutricional r2 ON a2.Id_RegistroNutricional = r2.Id GROUP BY r2.Id_Estudiante)";
    if (!empty($where_a)) $sql_alertas .= " " . str_replace("WHERE", "AND", $where_a);
    $sql_alertas .= " ORDER BY a.Estado DESC, r.FechaMedicion DESC LIMIT $registros_por_pagina OFFSET $offset_gen";
    $stmt_alertas = $pdo->prepare($sql_alertas); $stmt_alertas->execute($params_a);
}

// =================================================================================
// LÓGICA VISTA REPORTES (CON VALIDACIÓN DE FECHAS)
// =================================================================================
if ($vista === 'reportes') {
    $pag_rep = isset($_GET['pag_rep']) ? (int)$_GET['pag_rep'] : 1;
    if ($pag_rep < 1) $pag_rep = 1;
    $offset_rep = ($pag_rep - 1) * $registros_por_pagina;

    // Filtros
    $rep_colegio = $_GET['rep_colegio'] ?? '';
    $rep_curso = $_GET['rep_curso'] ?? '';
    $rep_sexo = $_GET['rep_sexo'] ?? '';
    
    // Validación: Si viene vacío, usar defaults
    $fecha_ini = !empty($_GET['fecha_ini']) ? $_GET['fecha_ini'] : date('Y-01-01');
    $fecha_fin = !empty($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

    // Query Base
    $cond_rep = ["r.FechaMedicion BETWEEN ? AND ?"];
    $params_rep = [$fecha_ini, $fecha_fin];

    if ($rep_colegio) { $cond_rep[] = "c.Id_Establecimiento = ?"; $params_rep[] = $rep_colegio; }
    if ($rep_curso) { $cond_rep[] = "c.Id = ?"; $params_rep[] = $rep_curso; }
    if ($rep_sexo) { $cond_rep[] = "e.Sexo = ?"; $params_rep[] = $rep_sexo; }

    $where_rep = "WHERE " . implode(" AND ", $cond_rep);

    // Contar Total
    $sql_count_rep = "SELECT COUNT(*) FROM RegistroNutricional r JOIN Estudiante e ON r.Id_Estudiante = e.Id JOIN Curso c ON e.Id_Curso = c.Id $where_rep";
    $stmt_count_rep = $pdo->prepare($sql_count_rep); $stmt_count_rep->execute($params_rep);
    $total_registros_rep = $stmt_count_rep->fetchColumn();
    $total_pags_rep = ceil($total_registros_rep / $registros_por_pagina);

    // Obtener Datos
    $sql_reporte = "
        SELECT e.Rut, CONCAT_WS(' ', e.Nombres, e.ApellidoPaterno, e.ApellidoMaterno) as Estudiante, e.Sexo,
            TIMESTAMPDIFF(YEAR, e.FechaNacimiento, CURDATE()) as Edad, c.Nombre as Curso, est.Nombre as Colegio,
            r.FechaMedicion, r.Peso, r.Altura, r.IMC, r.Diagnostico
        FROM RegistroNutricional r JOIN Estudiante e ON r.Id_Estudiante = e.Id JOIN Curso c ON e.Id_Curso = c.Id JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
        $where_rep
        ORDER BY est.Nombre, c.Nombre, e.ApellidoPaterno
        LIMIT $registros_por_pagina OFFSET $offset_rep
    ";
    $stmt_rep = $pdo->prepare($sql_reporte); $stmt_rep->execute($params_rep);
    $resultados_reporte = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard DAEM</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 4px solid #0d6efd; }
        .kpi-card .value { font-size: 2rem; font-weight: bold; color: #333; }
        .block-section { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .charts-row { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .chart-card { flex: 1; min-width: 300px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; }
        .chart-wrapper { position: relative; width: 100%; max-width: 350px; height: 300px; }
        .chart-wrapper-bar { position: relative; width: 100%; height: 300px; }

        .top-filters { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 25px; flex-wrap: wrap; }
        .page-link { padding: 8px 14px; border: 1px solid #ddd; background: white; text-decoration: none; border-radius: 4px; color: #333; font-weight: 500; transition: all 0.2s; }
        .page-link:hover { background-color: #f8f9fa; }
        .page-link.active { background: #0d6efd; color: white; border-color: #0d6efd; }
        .filter-tabs a { margin-right: 15px; text-decoration: none; padding-bottom: 5px; border-bottom: 2px solid transparent; color: #666; font-weight: 500; }
        .filter-tabs a.active { color: #0d6efd; border-bottom-color: #0d6efd; }
        .report-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .report-header-print { display: none; }

        @media print {
            @page { size: landscape; margin: 10mm; }
            body { font-family: 'Segoe UI', sans-serif; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sidebar, .header, .top-filters, .report-form-container, .actions-bar, .pagination, .btn-create, .nav-tabs { display: none !important; }
            .main-content { margin: 0; padding: 0; width: 100%; }
            .content-container { box-shadow: none; border: none; padding: 0; }
            .block-section { box-shadow: none; border: none; padding: 0; margin: 0; }
            
            /* Encabezado PDF sin manzana */
            .report-header-print { display: block !important; margin-bottom: 20px; border-bottom: 2px solid #4361ee; padding-bottom: 10px; }
            .rh-logo { font-size: 24px; font-weight: bold; color: #4361ee; float: left; }
            .rh-info { text-align: right; font-size: 12px; color: #666; float: right; margin-top: 5px; }
            .rh-clear { clear: both; }

            .table-responsive { overflow: visible; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th { background-color: #f3f4f6 !important; color: #111 !important; font-weight: 700; padding: 6px 4px; border-bottom: 2px solid #ccc; text-transform: uppercase; }
            td { padding: 4px; border-bottom: 1px solid #eee; color: #333; }
            tr:nth-child(even) { background-color: #f9f9f9 !important; }
            .text-danger { color: #dc3545 !important; font-weight: bold; }
            .text-warning { color: #ffc107 !important; font-weight: bold; }
            .text-orange { color: #fd7e14 !important; font-weight: bold; }
            .text-success { color: #198754 !important; font-weight: bold; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                <a href="dashboard_admin_daem.php?vista=general" class="nav-item <?php echo $vista=='general'?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> General</a>
                <a href="dashboard_admin_daem.php?vista=reportes" class="nav-item <?php echo $vista=='reportes'?'active':''; ?>"><i class="fa-solid fa-file-contract"></i> Generar Reportes</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div><a href="logout.php" class="btn-logout">Salir</a></header>
            <section class="content-body">
                <div class="content-container" style="background:transparent; box-shadow:none; padding:0;">
                    
                    <?php if ($vista === 'general'): ?>
                        <h1><i class="fa-solid fa-chart-line"></i> Monitor Nutricional</h1>
                        <div class="top-filters">
                            <i class="fa-solid fa-filter" style="color:#666;"></i>
                            <form style="display:flex; gap:10px; flex-grow:1;" method="GET">
                                <input type="hidden" name="vista" value="general">
                                <?php if(isset($_GET['ver'])): ?><input type="hidden" name="ver" value="<?php echo htmlspecialchars($_GET['ver']); ?>"><?php endif; ?>
                                <select name="colegio" onchange="this.form.submit()" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
                                    <option value="">🏢 Todos los Colegios</option>
                                    <?php foreach($colegios as $c): ?><option value="<?php echo $c['Id']; ?>" <?php echo $filtro_colegio == $c['Id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['Nombre']); ?></option><?php endforeach; ?>
                                </select>
                                <select name="sexo" onchange="this.form.submit()" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
                                    <option value="">🚻 Todos los Géneros</option>
                                    <option value="M" <?php echo $filtro_sexo == 'M' ? 'selected' : ''; ?>>Hombres</option>
                                    <option value="F" <?php echo $filtro_sexo == 'F' ? 'selected' : ''; ?>>Mujeres</option>
                                </select>
                            </form>
                        </div>
                        <div class="kpi-grid">
                            <div class="kpi-card"><h3>Total Alumnos</h3><div class="value"><?php echo $kpis['total']; ?></div></div>
                            <div class="kpi-card" style="border-left-color:#198754;"><h3>Promedio IMC</h3><div class="value"><?php echo number_format($kpis['prom_imc'],1); ?></div></div>
                            <div class="kpi-card" style="border-left-color:#dc3545;"><h3>Riesgo (Obes/Bajo)</h3><div class="value"><?php echo $porcentaje; ?>%</div></div>
                        </div>
                        <div class="charts-row">
                            <div class="chart-card"><h3 style="margin-bottom:15px; color:#444;">Distribución (Porcentaje)</h3><div class="chart-wrapper"><canvas id="grafico1"></canvas></div></div>
                            <div class="chart-card"><h3 style="margin-bottom:15px; color:#444;">Distribución (Cantidad)</h3><div class="chart-wrapper-bar"><canvas id="grafico2"></canvas></div></div>
                        </div>
                        <div class="block-section">
                            <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom: 10px;">
                                <h3><i class="fa-solid fa-bell" style="color: #fd7e14;"></i> Gestión de Alertas</h3>
                                <div class="filter-tabs">
                                    <a href="<?php echo buildUrl(['ver' => 'pendientes', 'pag' => 1]); ?>" class="<?php echo $filtro_estado === 1 ? 'active' : ''; ?>">Pendientes</a>
                                    <a href="<?php echo buildUrl(['ver' => 'todos', 'pag' => 1]); ?>" class="<?php echo $filtro_estado === null ? 'active' : ''; ?>">Historial Completo</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table style="width: 100%;">
                                    <thead><tr><th>Estado</th><th>Alumno</th><th>RUT</th><th>Colegio</th><th>IMC</th><th>Diag.</th><th>Fecha</th><th>Acción</th></tr></thead>
                                    <tbody>
                                        <?php while($row = $stmt_alertas->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo $row['Estado']==1 ? '<span class="status-inactive">Pendiente</span>' : '<span class="status-active">Atendida</span>'; ?></td>
                                            <td><strong><?php echo htmlspecialchars($row['Estudiante']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['Rut']); ?></td>
                                            <td><small><?php echo htmlspecialchars($row['Establecimiento']); ?></small></td>
                                            <td><b><?php echo $row['IMC']; ?></b></td>
                                            <td><?php $colorD='#333'; $d=$row['Diagnostico']; if(strpos($d,'Bajo')!==false)$colorD='#ffc107'; elseif(strpos($d,'Normal')!==false)$colorD='#198754'; elseif(strpos($d,'Sobrepeso')!==false)$colorD='#fd7e14'; elseif(strpos($d,'Obesidad')!==false)$colorD='#dc3545'; echo "<span style='color:$colorD; font-weight:bold;'>$d</span>"; ?></td>
                                            <td><?php echo date("d/m/Y", strtotime($row['FechaMedicion'])); ?></td>
                                            <td class="actions"><a href="AdminDAEM/gestionar_alerta.php?id=<?php echo $row['IdAlerta']; ?>" class="btn-action btn-edit"><i class="fa-solid fa-file-pen"></i></a></td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if($stmt_alertas->rowCount() == 0): ?><tr><td colspan="8" style="text-align:center; padding:30px;">No hay alertas.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($total_pags_alertas > 1): ?>
                            <div class="pagination">
                                <?php for($i=1; $i<=$total_pags_alertas; $i++): ?>
                                    <a href="<?php echo buildUrl(['pag' => $i]); ?>" class="page-link <?php echo $i==$pag_general?'active':''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($vista === 'reportes'): ?>
                        <h1><i class="fa-solid fa-file-contract"></i> Generador de Reportes</h1>
                        
                        <div class="block-section report-form-container">
                            <form method="GET" class="report-filters" id="formReportes">
                                <input type="hidden" name="vista" value="reportes">
                                
                                <div class="form-group">
                                    <label>Colegio:</label>
                                    <select name="rep_colegio" id="rep_colegio" onchange="this.form.submit()">
                                        <option value="">Todos los Colegios</option>
                                        <?php foreach($colegios as $c): ?>
                                            <option value="<?php echo $c['Id']; ?>" <?php echo $rep_colegio == $c['Id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['Nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Curso:</label>
                                    <select name="rep_curso" id="rep_curso" onchange="this.form.submit()">
                                        <option value="">Todos los Cursos</option>
                                        </select>
                                </div>

                                <div class="form-group">
                                    <label>Desde:</label>
                                    <input type="date" name="fecha_ini" value="<?php echo $fecha_ini; ?>" onchange="actualizarReporteConDebounce()">
                                </div>

                                <div class="form-group">
                                    <label>Hasta:</label>
                                    <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" onchange="actualizarReporteConDebounce()">
                                </div>

                                <div class="form-group">
                                    <label>Sexo:</label>
                                    <select name="rep_sexo" onchange="this.form.submit()">
                                        <option value="">Todos</option>
                                        <option value="M" <?php echo $rep_sexo == 'M' ? 'selected' : ''; ?>>Hombres</option>
                                        <option value="F" <?php echo $rep_sexo == 'F' ? 'selected' : ''; ?>>Mujeres</option>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <div class="block-section">
                            <div class="actions-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                                <h3>Resultados (<?php echo $total_registros_rep; ?> registros)</h3>
                                <div style="display:flex; gap:10px;">
                                    <button onclick="window.print()" class="btn-create" style="background:#6c757d;"><i class="fa-solid fa-print"></i> Imprimir PDF</button>
                                    <a href="AdminDAEM/exportar_excel.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn-create" style="background:#198754;"><i class="fa-solid fa-file-excel"></i> Exportar Excel</a>
                                </div>
                            </div>

                            <div class="report-header-print">
                                <div class="rh-logo">NutriData Reporte</div>
                                <div class="rh-info">
                                    Generado: <?php echo date("d/m/Y H:i"); ?><br>
                                    Filtro: <?php echo $rep_colegio ? 'Colegio Seleccionado' : 'Global'; ?><br>
                                    Rango: <?php echo date("d/m/Y", strtotime($fecha_ini)); ?> al <?php echo date("d/m/Y", strtotime($fecha_fin)); ?>
                                </div>
                                <div class="rh-clear"></div>
                            </div>

                            <div class="table-responsive">
                                <table style="width: 100%;">
                                    <thead>
                                        <tr style="background:#f3f4f6;">
                                            <th>RUT</th><th>Estudiante</th><th>Sexo</th><th>Edad</th><th>Curso</th><th>Colegio</th><th>IMC</th><th>Diagnóstico</th><th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($resultados_reporte)): ?>
                                            <tr><td colspan="9" style="text-align:center; padding:20px;">No se encontraron datos.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($resultados_reporte as $row): ?>
                                            <tr>
                                                <td><?php echo $row['Rut']; ?></td>
                                                <td><?php echo htmlspecialchars($row['Estudiante']); ?></td>
                                                <td style="text-align:center;"><?php echo $row['Sexo']; ?></td>
                                                <td style="text-align:center;"><?php echo $row['Edad']; ?></td>
                                                <td><?php echo htmlspecialchars($row['Curso']); ?></td>
                                                <td><?php echo htmlspecialchars($row['Colegio']); ?></td>
                                                <td><strong><?php echo $row['IMC']; ?></strong></td>
                                                <td>
                                                    <?php 
                                                    $d = $row['Diagnostico']; $cls = 'text-dark';
                                                    if(strpos($d,'Obesidad')!==false) $cls='text-danger';
                                                    elseif(strpos($d,'Bajo')!==false) $cls='text-warning';
                                                    elseif(strpos($d,'Sobrepeso')!==false) $cls='text-orange';
                                                    elseif(strpos($d,'Normal')!==false) $cls='text-success';
                                                    echo "<span class='$cls'>$d</span>";
                                                    ?>
                                                </td>
                                                <td><?php echo date("d/m/Y", strtotime($row['FechaMedicion'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($total_pags_rep > 1): ?>
                            <div class="pagination">
                                <?php for($i=1; $i<=$total_pags_rep; $i++): ?>
                                    <a href="<?php echo buildUrl(['pag_rep' => $i]); ?>" class="page-link <?php echo $i==$pag_rep?'active':''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                            <div style="text-align:center; margin-top:10px; color:#888; font-size:0.8rem;" class="actions-bar">Página <?php echo $pag_rep; ?> de <?php echo $total_pags_rep; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </section>
        </main>
    </div>

    <?php if ($vista === 'general'): ?>
    <script>
        const labels = <?php echo json_encode($labels); ?>;
        const data = <?php echo json_encode($data); ?>;
        const colores = <?php echo json_encode($colores); ?>;
        new Chart(document.getElementById('grafico1'), { type: 'doughnut', data: { labels: labels, datasets: [{ data: data, backgroundColor: colores, borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
        new Chart(document.getElementById('grafico2'), { type: 'bar', data: { labels: labels, datasets: [{ label: 'Cantidad', data: data, backgroundColor: colores, borderRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } } });
    </script>
    <?php endif; ?>

    <?php if ($vista === 'reportes'): ?>
    <script>
        // 1. Debounce para Fechas (Evita recarga inmediata)
        let timeout;
        function actualizarReporteConDebounce() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                document.getElementById('formReportes').submit();
            }, 1000); // Espera 1 segundo después de cambiar la fecha
        }

        // 2. Filtro de Cursos Dinámico
        const todosLosCursos = <?php echo json_encode($todos_los_cursos); ?>;
        const cursoSeleccionado = "<?php echo $rep_curso; ?>";

        function filtrarCursosJS() {
            const colegioId = document.getElementById('rep_colegio').value;
            const selectCurso = document.getElementById('rep_curso');
            selectCurso.innerHTML = '<option value="">Todos los Cursos</option>';
            const cursosFiltrados = colegioId ? todosLosCursos.filter(c => c.Id_Establecimiento == colegioId) : todosLosCursos;
            cursosFiltrados.forEach(c => {
                const option = document.createElement('option');
                option.value = c.Id;
                option.textContent = c.Nombre;
                if(c.Id == cursoSeleccionado) option.selected = true;
                selectCurso.appendChild(option);
            });
        }
        document.addEventListener('DOMContentLoaded', filtrarCursosJS);
    </script>
    <?php endif; ?>
</body>
</html>