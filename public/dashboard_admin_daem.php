<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: login.php"); exit;
}

// --- 1. CONFIGURACIÓN ---
$registros_por_pagina = 20; 
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Filtros
$filtro_estado = isset($_GET['ver']) && $_GET['ver'] === 'todos' ? null : 1; // null = todos, 1 = pendientes
$filtro_colegio = isset($_GET['colegio']) && $_GET['colegio'] != '' ? $_GET['colegio'] : null;
$filtro_sexo = isset($_GET['sexo']) && $_GET['sexo'] != '' ? $_GET['sexo'] : null;

$colegios = $pdo->query("SELECT Id, Nombre FROM Establecimiento ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

function buildUrl($params = []) {
    $currentParams = $_GET;
    $merged = array_merge($currentParams, $params);
    return '?' . http_build_query($merged);
}

// --- 2. WHERE DINÁMICO ---
$cond = []; $params = [];
if ($filtro_colegio) { $cond[] = "c.Id_Establecimiento = ?"; $params[] = $filtro_colegio; }
if ($filtro_sexo) { $cond[] = "e.Sexo = ?"; $params[] = $filtro_sexo; }
$where_general = !empty($cond) ? " WHERE " . implode(" AND ", $cond) : "";

// --- 3. DATOS ---

// KPI: Totales
$sql_kpi = "
    SELECT 
        COUNT(*) as total,
        AVG(r.IMC) as prom_imc,
        SUM(CASE WHEN r.Diagnostico IN ('Bajo Peso', 'Obesidad', 'Obesidad Severa') THEN 1 ELSE 0 END) as riesgo
    FROM RegistroNutricional r
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    INNER JOIN (SELECT Id_Estudiante, MAX(FechaMedicion) as MaxF FROM RegistroNutricional GROUP BY Id_Estudiante) u 
    ON r.Id_Estudiante = u.Id_Estudiante AND r.FechaMedicion = u.MaxF
    $where_general
";
$stmt_kpi = $pdo->prepare($sql_kpi);
$stmt_kpi->execute($params);
$kpis = $stmt_kpi->fetch(PDO::FETCH_ASSOC);
$porcentaje = ($kpis['total'] > 0) ? round(($kpis['riesgo'] / $kpis['total']) * 100, 1) : 0;

// DATOS PARA GRÁFICOS (Se usan los mismos datos para ambos gráficos)
$sql_graf = "
    SELECT r.Diagnostico as estado, COUNT(*) as cantidad
    FROM RegistroNutricional r
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    INNER JOIN (SELECT Id_Estudiante, MAX(FechaMedicion) as MaxF FROM RegistroNutricional GROUP BY Id_Estudiante) u 
    ON r.Id_Estudiante = u.Id_Estudiante AND r.FechaMedicion = u.MaxF
    $where_general
    GROUP BY r.Diagnostico
";
$stmt_graf = $pdo->prepare($sql_graf);
$stmt_graf->execute($params);
$datos_graf = $stmt_graf->fetchAll(PDO::FETCH_ASSOC);

$labels = []; $data = []; $colores = [];
foreach ($datos_graf as $fila) {
    $labels[] = $fila['estado']; 
    $data[] = $fila['cantidad'];
    
    // Colores Estandarizados (Igual que antes)
    if(strpos($fila['estado'], 'Bajo') !== false) $colores[]='#ffc107';      // Amarillo
    elseif(strpos($fila['estado'], 'Normal') !== false) $colores[]='#198754'; // Verde
    elseif(strpos($fila['estado'], 'Sobrepeso') !== false) $colores[]='#fd7e14'; // Naranja
    else $colores[]='#dc3545';                                  // Rojo (Obesidad)
}

// TABLA ALERTAS
$sql_base_alertas = "
    FROM Alerta a
    JOIN RegistroNutricional r ON a.Id_RegistroNutricional = r.Id
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
";

$cond_a = []; $params_a = [];
if ($filtro_estado !== null) { $cond_a[] = "a.Estado = ?"; $params_a[] = $filtro_estado; }
if ($filtro_colegio) { $cond_a[] = "c.Id_Establecimiento = ?"; $params_a[] = $filtro_colegio; }
if ($filtro_sexo) { $cond_a[] = "e.Sexo = ?"; $params_a[] = $filtro_sexo; }

$where_a = !empty($cond_a) ? " WHERE " . implode(" AND ", $cond_a) : "";

// Conteo total para paginación
$sql_conteo = "SELECT COUNT(*) " . $sql_base_alertas . $where_a;
$stmt_c = $pdo->prepare($sql_conteo); 
$stmt_c->execute($params_a);
$total_regs = $stmt_c->fetchColumn();
$total_pags = ceil($total_regs / $registros_por_pagina);

// Consulta Tabla (Ahora incluye e.Rut)
$sql_alertas = "
    SELECT 
        a.Id as IdAlerta, a.Estado, 
        CONCAT_WS(' ', e.Nombres, e.ApellidoPaterno, e.ApellidoMaterno) as Estudiante,
        e.Rut,  -- <--- RUT AGREGADO
        est.Nombre as Establecimiento, 
        r.IMC, r.FechaMedicion, r.Diagnostico 
    " . $sql_base_alertas . "
    WHERE a.Id IN (
        SELECT MAX(a2.Id)
        FROM Alerta a2
        JOIN RegistroNutricional r2 ON a2.Id_RegistroNutricional = r2.Id
        GROUP BY r2.Id_Estudiante
    )
";

if (!empty($where_a)) {
    $filtro_extra = str_replace("WHERE", "AND", $where_a); 
    $sql_alertas .= " " . $filtro_extra;
}

$sql_alertas .= " ORDER BY a.Estado DESC, r.FechaMedicion DESC LIMIT $registros_por_pagina OFFSET $offset";

$stmt_alertas = $pdo->prepare($sql_alertas);
$stmt_alertas->execute($params_a);
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
        
        .block-section {
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        /* Contenedor de Gráficos lado a lado */
        .charts-row {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .chart-card {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .chart-wrapper {
            position: relative;
            width: 100%;
            max-width: 350px; /* Controla el tamaño */
            height: 300px;
        }
        .chart-wrapper-bar {
            position: relative;
            width: 100%;
            height: 300px;
        }

        .top-filters { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 25px; flex-wrap: wrap; }
        .page-link { 
            padding: 8px 14px; 
            border: 1px solid #ddd; 
            background: white; 
            text-decoration: none; 
            border-radius: 4px; 
            color: #333; 
            font-weight: 500;
            transition: all 0.2s;
        }
        .page-link:hover { background-color: #f8f9fa; }
        .page-link.active { background: #0d6efd; color: white; border-color: #0d6efd; }
        
        .filter-tabs a { margin-right: 15px; text-decoration: none; padding-bottom: 5px; border-bottom: 2px solid transparent; color: #666; font-weight: 500; }
        .filter-tabs a.active { color: #0d6efd; border-bottom-color: #0d6efd; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav"><a href="#" class="nav-item active"><i class="fa-solid fa-chart-pie"></i> General</a></nav>
        </aside>
        <main class="main-content">
            <header class="header"><div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div><a href="logout.php" class="btn-logout">Salir</a></header>
            <section class="content-body">
                <div class="content-container" style="background:transparent; box-shadow:none; padding:0;">
                    <h1><i class="fa-solid fa-chart-line"></i> Monitor Nutricional</h1>
                    
                    <div class="top-filters">
                        <i class="fa-solid fa-filter" style="color:#666;"></i>
                        <form style="display:flex; gap:10px; flex-grow:1;" method="GET">
                            <?php if(isset($_GET['ver'])): ?><input type="hidden" name="ver" value="<?php echo htmlspecialchars($_GET['ver']); ?>"><?php endif; ?>
                            
                            <select name="colegio" onchange="this.form.submit()" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
                                <option value="">🏢 Todos los Colegios</option>
                                <?php foreach($colegios as $c): ?>
                                    <option value="<?php echo $c['Id']; ?>" <?php echo $filtro_colegio == $c['Id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['Nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
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
                        <div class="chart-card">
                            <h3 style="margin-bottom:15px; color:#444;">Distribución (Porcentaje)</h3>
                            <div class="chart-wrapper">
                                <canvas id="grafico1"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <h3 style="margin-bottom:15px; color:#444;">Distribución (Cantidad)</h3>
                            <div class="chart-wrapper-bar">
                                <canvas id="grafico2"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="block-section">
                        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom: 10px;">
                            <h3><i class="fa-solid fa-bell" style="color: #fd7e14;"></i> Gestión de Alertas</h3>
                            <div class="filter-tabs">
                                <a href="<?php echo buildUrl(['ver' => 'pendientes', 'pag' => 1]); ?>" 
                                   class="<?php echo $filtro_estado === 1 ? 'active' : ''; ?>">
                                   Pendientes
                                </a>
                                <a href="<?php echo buildUrl(['ver' => 'todos', 'pag' => 1]); ?>" 
                                   class="<?php echo $filtro_estado === null ? 'active' : ''; ?>">
                                   Historial Completo
                                </a>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Alumno</th>
                                        <th>RUT</th> <th>Colegio</th>
                                        <th>IMC</th>
                                        <th>Diag.</th>
                                        <th>Fecha</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $stmt_alertas->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $row['Estado']==1 ? '<span class="status-inactive">Pendiente</span>' : '<span class="status-active">Atendida</span>'; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['Estudiante']); ?></strong></td>
                                        
                                        <td><?php echo htmlspecialchars($row['Rut']); ?></td>
                                        
                                        <td><small><?php echo htmlspecialchars($row['Establecimiento']); ?></small></td>
                                        <td><b><?php echo $row['IMC']; ?></b></td>
                                        <td>
                                            <?php 
                                            // Lógica de colores diagnósticos (Debe coincidir con los gráficos)
                                            $colorD = '#dc3545'; // Default Rojo
                                            $d = $row['Diagnostico'];
                                            
                                            if(strpos($d, 'Bajo') !== false) $colorD = '#ffc107'; // Amarillo
                                            elseif(strpos($d, 'Normal') !== false) $colorD = '#198754'; // Verde
                                            elseif(strpos($d, 'Sobrepeso') !== false) $colorD = '#fd7e14'; // Naranja
                                            
                                            echo "<span style='color:$colorD; font-weight:bold;'>".$d."</span>"; 
                                            ?>
                                        </td>
                                        <td><?php echo date("d/m/Y", strtotime($row['FechaMedicion'])); ?></td>
                                        <td class="actions">
                                            <a href="AdminDAEM/gestionar_alerta.php?id=<?php echo $row['IdAlerta']; ?>" class="btn-action btn-edit" title="Gestionar Caso">
                                                <i class="fa-solid fa-file-pen"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if($stmt_alertas->rowCount() == 0): ?>
                                        <tr><td colspan="8" style="text-align:center; color:#999; padding:30px;">No hay alertas que coincidan con los filtros.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pags > 1): ?>
                        <div class="pagination">
                            <?php 
                            if($pagina_actual > 1) {
                                echo '<a href="'.buildUrl(['pag' => $pagina_actual-1]).'" class="page-link">&laquo;</a>';
                            }
                            for($i=1; $i<=$total_pags; $i++): 
                            ?>
                                <a href="<?php echo buildUrl(['pag' => $i]); ?>" 
                                   class="page-link <?php echo $i==$pagina_actual?'active':''; ?>">
                                   <?php echo $i; ?>
                                </a>
                            <?php endfor; 
                            if($pagina_actual < $total_pags) {
                                echo '<a href="'.buildUrl(['pag' => $pagina_actual+1]).'" class="page-link">&raquo;</a>';
                            }
                            ?>
                        </div>
                        <div style="text-align:center; margin-top:10px; color:#888; font-size:0.9rem;">
                            Página <?php echo $pagina_actual; ?> de <?php echo $total_pags; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </section>
        </main>
    </div>
    <script>
        // Datos comunes
        const labels = <?php echo json_encode($labels); ?>;
        const data = <?php echo json_encode($data); ?>;
        const colores = <?php echo json_encode($colores); ?>;

        // GRÁFICO 1: Dona (Porcentaje/Distribución)
        new Chart(document.getElementById('grafico1'), {
            type: 'doughnut',
            data: { 
                labels: labels, 
                datasets: [{ 
                    data: data, 
                    backgroundColor: colores, 
                    borderWidth: 1 
                }] 
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } } 
            }
        });

        // GRÁFICO 2: Barras (Mismos datos, diferente vista)
        new Chart(document.getElementById('grafico2'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Cantidad de Estudiantes',
                    data: data,
                    backgroundColor: colores, // Mismos colores para consistencia
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>