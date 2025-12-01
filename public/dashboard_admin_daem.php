<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN DE SEGURIDAD ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: login.php");
    exit;
}

// --- CONFIGURACIÓN DE PAGINACIÓN Y FILTROS ---
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Filtros
$filtro_estado = isset($_GET['ver']) && $_GET['ver'] === 'todos' ? null : 1; // Por defecto solo pendientes
$filtro_colegio = isset($_GET['colegio']) && $_GET['colegio'] != '' ? $_GET['colegio'] : null;

// Obtener lista de colegios para el select
$colegios = $pdo->query("SELECT Id, Nombre FROM Establecimiento ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

// Clausula WHERE dinámica para los KPIs y Gráficos
$where_kpi = "";
$params_kpi = [];
if ($filtro_colegio) {
    // Filtramos por el ID del establecimiento (JOIN necesario con Curso)
    $where_kpi = " WHERE c.Id_Establecimiento = ? ";
    $params_kpi[] = $filtro_colegio;
}

// --- 1. KPIs GENERALES (Filtrables) ---
$sql_kpi = "
    SELECT 
        COUNT(*) as total_mediciones,
        AVG(r.IMC) as promedio_imc,
        SUM(CASE WHEN r.IMC < 18.5 OR r.IMC >= 25 THEN 1 ELSE 0 END) as casos_riesgo
    FROM RegistroNutricional r
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    INNER JOIN (
        SELECT Id_Estudiante, MAX(FechaMedicion) as MaxFecha
        FROM RegistroNutricional
        GROUP BY Id_Estudiante
    ) ultimos ON r.Id_Estudiante = ultimos.Id_Estudiante AND r.FechaMedicion = ultimos.MaxFecha
    $where_kpi
";
$stmt_kpi = $pdo->prepare($sql_kpi);
$stmt_kpi->execute($params_kpi);
$kpis = $stmt_kpi->fetch(PDO::FETCH_ASSOC);

$porcentaje_riesgo = ($kpis['total_mediciones'] > 0) ? round(($kpis['casos_riesgo'] / $kpis['total_mediciones']) * 100, 1) : 0;

// --- 2. GRÁFICO (Filtrable) ---
$sql_grafico = "
    SELECT 
        CASE 
            WHEN r.IMC < 18.5 THEN 'Bajo Peso'
            WHEN r.IMC BETWEEN 18.5 AND 24.9 THEN 'Normal'
            WHEN r.IMC BETWEEN 25 AND 29.9 THEN 'Sobrepeso'
            ELSE 'Obesidad'
        END as estado,
        COUNT(*) as cantidad
    FROM RegistroNutricional r
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    INNER JOIN (
        SELECT Id_Estudiante, MAX(FechaMedicion) as MaxFecha
        FROM RegistroNutricional
        GROUP BY Id_Estudiante
    ) ultimos ON r.Id_Estudiante = ultimos.Id_Estudiante AND r.FechaMedicion = ultimos.MaxFecha
    $where_kpi
    GROUP BY estado
";
$stmt_grafico = $pdo->prepare($sql_grafico);
$stmt_grafico->execute($params_kpi);
$datos_grafico = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

// Datos para Chart.js
$labels = []; $data = []; $colores = [];
foreach ($datos_grafico as $fila) {
    $labels[] = $fila['estado'];
    $data[] = $fila['cantidad'];
    switch($fila['estado']) {
        case 'Bajo Peso': $colores[] = '#ffc107'; break;
        case 'Normal': $colores[] = '#198754'; break;
        case 'Sobrepeso': $colores[] = '#fd7e14'; break;
        case 'Obesidad': $colores[] = '#dc3545'; break;
    }
}

// --- 3. TABLA DE ALERTAS (Filtrable y Paginada) ---
// Construcción dinámica de la consulta base
$sql_base = "
    FROM Alerta a
    JOIN RegistroNutricional r ON a.Id_RegistroNutricional = r.Id
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
";

// Construir WHERE para alertas
$condiciones = [];
$params_alertas = [];

if ($filtro_estado !== null) {
    $condiciones[] = "a.Estado = ?";
    $params_alertas[] = $filtro_estado;
}
if ($filtro_colegio) {
    $condiciones[] = "c.Id_Establecimiento = ?";
    $params_alertas[] = $filtro_colegio;
}

$where_alertas = !empty($condiciones) ? "WHERE " . implode(" AND ", $condiciones) : "";

// Contar total para paginación
$sql_conteo = "SELECT COUNT(*) " . $sql_base . $where_alertas;
$stmt_conteo = $pdo->prepare($sql_conteo);
$stmt_conteo->execute($params_alertas);
$total_registros = $stmt_conteo->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta Final con LIMIT
$sql_alertas = "
    SELECT 
        a.Id as IdAlerta,
        a.Estado,
        e.Nombre as Estudiante,
        e.Rut,
        c.Nombre as Curso,
        est.Nombre as Establecimiento,
        r.IMC,
        r.FechaMedicion
    " . $sql_base . $where_alertas . "
    ORDER BY a.Estado DESC, r.FechaMedicion DESC
    LIMIT $registros_por_pagina OFFSET $offset
";
$stmt_alertas = $pdo->prepare($sql_alertas);
$stmt_alertas->execute($params_alertas);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard DAEM - NutriMonitor</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 4px solid #0d6efd; }
        .kpi-card h3 { font-size: 0.9rem; color: #666; margin-bottom: 10px; }
        .kpi-card .value { font-size: 2rem; font-weight: bold; color: #333; }
        .kpi-card .subtext { font-size: 0.8rem; color: #999; }
        
        .charts-container { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .chart-box { flex: 1; min-width: 300px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 12px; border: 1px solid #ddd; background: white; color: #333; text-decoration: none; border-radius: 4px; }
        .page-link.active { background: #0d6efd; color: white; border-color: #0d6efd; }
        
        /* Filtros */
        .top-filters { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-tabs { display: flex; gap: 5px; }
        .filter-tab { padding: 6px 15px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: 500; border: 1px solid transparent; }
        .filter-tab.active { background-color: #eef2ff; color: #4361ee; border-color: #4361ee; }
        .filter-tab.inactive { background-color: #f8f9fa; color: #666; border-color: #e5e7eb; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                <div class="nav-category">Reportes</div>
                <a href="dashboard_admin_daem.php" class="nav-item active"><i class="fa-solid fa-chart-pie"></i> Panorama General</a>
                <a href="#" class="nav-item" style="opacity: 0.5;"><i class="fa-solid fa-file-pdf"></i> Exportar Informes</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?> (DAEM)</div>
                <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
            </header>

            <section class="content-body">
                <div class="content-container" style="background: transparent; box-shadow: none; padding: 0;">
                    
                    <div class="content-header-with-btn">
                        <h1><i class="fa-solid fa-chart-line"></i> Monitor Nutricional</h1>
                    </div>

                    <div class="top-filters">
                        <i class="fa-solid fa-filter" style="color: #666;"></i>
                        <form action="" method="GET" style="display:flex; align-items:center; gap:10px; flex-grow:1;">
                            <?php if(isset($_GET['ver'])): ?>
                                <input type="hidden" name="ver" value="<?php echo htmlspecialchars($_GET['ver']); ?>">
                            <?php endif; ?>
                            
                            <select name="colegio" onchange="this.form.submit()" style="max-width: 300px; padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                                <option value="">🏢 Ver Todos los Establecimientos</option>
                                <?php foreach($colegios as $col): ?>
                                    <option value="<?php echo $col['Id']; ?>" <?php echo ($filtro_colegio == $col['Id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($col['Nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <h3>Total Estudiantes</h3>
                            <div class="value"><?php echo number_format($kpis['total_mediciones']); ?></div>
                            <div class="subtext">Medidos en este periodo</div>
                        </div>
                        <div class="kpi-card" style="border-left-color: #198754;">
                            <h3>Promedio IMC</h3>
                            <div class="value"><?php echo number_format($kpis['promedio_imc'], 1); ?></div>
                            <div class="subtext">
                                <?php echo ($filtro_colegio) ? 'En el establecimiento' : 'Nivel Comunal'; ?>
                            </div>
                        </div>
                        <div class="kpi-card" style="border-left-color: #dc3545;">
                            <h3>Casos de Riesgo</h3>
                            <div class="value"><?php echo $porcentaje_riesgo; ?>%</div>
                            <div class="subtext"><?php echo $kpis['casos_riesgo']; ?> estudiantes críticos</div>
                        </div>
                    </div>

                    <div class="charts-container">
                        <div class="chart-box" style="flex: 0 0 350px;">
                            <h3>Estado Nutricional</h3>
                            <canvas id="graficoNutricional"></canvas>
                        </div>

                        <div class="chart-box" style="flex: 1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                <h3><i class="fa-solid fa-bell"></i> Gestión de Alertas</h3>
                                <div class="filter-tabs">
                                    <a href="?ver=pendientes&colegio=<?php echo $filtro_colegio; ?>" class="filter-tab <?php echo ($filtro_estado === 1) ? 'active' : 'inactive'; ?>">Pendientes</a>
                                    <a href="?ver=todos&colegio=<?php echo $filtro_colegio; ?>" class="filter-tab <?php echo ($filtro_estado === null) ? 'active' : 'inactive'; ?>">Historial</a>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Estado</th>
                                            <th>Estudiante</th>
                                            <th>Establecimiento</th>
                                            <th>IMC</th>
                                            <th>Fecha</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($alerta = $stmt_alertas->fetch(PDO::FETCH_ASSOC)): 
                                            $color_imc = ($alerta['IMC'] >= 30 || $alerta['IMC'] < 16) ? '#dc3545' : '#fd7e14';
                                            $icono_estado = ($alerta['Estado'] == 1) 
                                                ? '<span class="status-inactive" style="font-size:0.75rem;">Pendiente</span>' 
                                                : '<span class="status-active" style="font-size:0.75rem;">Atendida</span>';
                                        ?>
                                        <tr>
                                            <td><?php echo $icono_estado; ?></td>
                                            <td><?php echo htmlspecialchars($alerta['Estudiante']); ?></td>
                                            <td><small><?php echo htmlspecialchars($alerta['Establecimiento']); ?></small></td>
                                            <td style="font-weight: bold; color: <?php echo $color_imc; ?>"><?php echo $alerta['IMC']; ?></td>
                                            <td><?php echo date("d/m/Y", strtotime($alerta['FechaMedicion'])); ?></td>
                                            <td class="actions">
                                                <a href="AdminDAEM/gestionar_alerta.php?id=<?php echo $alerta['IdAlerta']; ?>" class="btn-action btn-edit" title="Gestionar">
                                                    <i class="fa-solid fa-file-pen"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if($stmt_alertas->rowCount() == 0): ?>
                                            <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">
                                                No hay alertas <?php echo ($filtro_estado===1) ? 'pendientes' : ''; ?> para mostrar.
                                            </td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($total_paginas > 1): ?>
                            <div class="pagination">
                                <?php if ($pagina_actual > 1): ?>
                                    <a href="?pag=<?php echo $pagina_actual - 1; ?>&ver=<?php echo $filtro_estado === null ? 'todos' : 'pendientes'; ?>&colegio=<?php echo $filtro_colegio; ?>" class="page-link">&laquo;</a>
                                <?php endif; ?>

                                <span style="padding: 8px 12px; color: #666;">Pág <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></span>

                                <?php if ($pagina_actual < $total_paginas): ?>
                                    <a href="?pag=<?php echo $pagina_actual + 1; ?>&ver=<?php echo $filtro_estado === null ? 'todos' : 'pendientes'; ?>&colegio=<?php echo $filtro_colegio; ?>" class="page-link">&raquo;</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('graficoNutricional').getContext('2d');
        const graficoNutricional = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: <?php echo json_encode($colores); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    </script>
</body>
</html>