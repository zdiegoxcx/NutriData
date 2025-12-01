<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: login.php"); exit;
}

// --- CONFIGURACIÓN DE PAGINACIÓN Y FILTROS ---
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener Filtros (Con persistencia)
$filtro_estado = isset($_GET['ver']) && $_GET['ver'] === 'todos' ? null : 1; // null = todos, 1 = pendientes
$filtro_colegio = isset($_GET['colegio']) && $_GET['colegio'] != '' ? $_GET['colegio'] : null;
$filtro_sexo = isset($_GET['sexo']) && $_GET['sexo'] != '' ? $_GET['sexo'] : null;

// Obtener lista de colegios para el select
$colegios = $pdo->query("SELECT Id, Nombre FROM Establecimiento ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

// --- HELPER PARA GENERAR URLs DE FILTROS ---
// Esta función es la clave: toma los parámetros actuales y solo cambia los que le pases.
function buildUrl($params = []) {
    $currentParams = $_GET;
    $merged = array_merge($currentParams, $params);
    return '?' . http_build_query($merged);
}

// --- WHERE DINÁMICO (Común para KPIs y Gráficos) ---
$cond = []; $params = [];
if ($filtro_colegio) { $cond[] = "c.Id_Establecimiento = ?"; $params[] = $filtro_colegio; }
if ($filtro_sexo) { $cond[] = "e.Sexo = ?"; $params[] = $filtro_sexo; }
$where_general = !empty($cond) ? " WHERE " . implode(" AND ", $cond) : "";

// --- 1. KPIs (Filtrables) ---
$sql_kpi = "
    SELECT 
        COUNT(*) as total,
        AVG(r.IMC) as prom_imc,
        SUM(CASE WHEN r.Diagnostico IN ('Bajo Peso', 'Obesidad') THEN 1 ELSE 0 END) as riesgo
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

// --- 2. GRÁFICO (Filtrable) ---
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
    $labels[] = $fila['estado']; $data[] = $fila['cantidad'];
    if($fila['estado']=='Bajo Peso') $colores[]='#ffc107';
    elseif($fila['estado']=='Normal') $colores[]='#198754';
    elseif($fila['estado']=='Sobrepeso') $colores[]='#fd7e14';
    else $colores[]='#dc3545';
}

// --- 3. TABLA DE ALERTAS (Filtrable y Paginada) ---
$sql_base_alertas = "
    FROM Alerta a
    JOIN RegistroNutricional r ON a.Id_RegistroNutricional = r.Id
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
";

// Construir condiciones específicas para alertas
$cond_a = []; $params_a = [];
// Filtro de estado (Pendiente/Todas)
if ($filtro_estado !== null) { $cond_a[] = "a.Estado = ?"; $params_a[] = $filtro_estado; }
// Filtros heredados (Colegio/Sexo)
if ($filtro_colegio) { $cond_a[] = "c.Id_Establecimiento = ?"; $params_a[] = $filtro_colegio; }
if ($filtro_sexo) { $cond_a[] = "e.Sexo = ?"; $params_a[] = $filtro_sexo; }

$where_a = !empty($cond_a) ? " WHERE " . implode(" AND ", $cond_a) : "";

// Contar total
$sql_conteo = "SELECT COUNT(*) " . $sql_base_alertas . $where_a;
$stmt_c = $pdo->prepare($sql_conteo); $stmt_c->execute($params_a);
$total_regs = $stmt_c->fetchColumn();
$total_pags = ceil($total_regs / $registros_por_pagina);

// Consulta Final
$sql_alertas = "
    SELECT 
        a.Id as IdAlerta, a.Estado, 
        e.Nombre as Estudiante, est.Nombre as Establecimiento, 
        r.IMC, r.FechaMedicion, r.Diagnostico 
    " . $sql_base_alertas . $where_a . " 
    ORDER BY a.Estado DESC, r.FechaMedicion DESC 
    LIMIT $registros_por_pagina OFFSET $offset
";
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
        .charts-container { display: flex; gap: 20px; flex-wrap: wrap; }
        .chart-box { flex: 1; min-width: 300px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .top-filters { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 12px; border: 1px solid #ddd; background: white; text-decoration: none; border-radius: 4px; color: #333; }
        .page-link.active { background: #0d6efd; color: white; border-color: #0d6efd; }
        /* Estilos pestañas */
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
                    
                    <!-- BARRA DE FILTROS (Mantiene los valores al recargar) -->
                    <div class="top-filters">
                        <i class="fa-solid fa-filter" style="color:#666;"></i>
                        <form style="display:flex; gap:10px; flex-grow:1;" method="GET">
                            <!-- Mantenemos la pestaña actual -->
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

                    <!-- KPIs -->
                    <div class="kpi-grid">
                        <div class="kpi-card"><h3>Total Alumnos</h3><div class="value"><?php echo $kpis['total']; ?></div></div>
                        <div class="kpi-card" style="border-left-color:#198754;"><h3>Promedio IMC</h3><div class="value"><?php echo number_format($kpis['prom_imc'],1); ?></div></div>
                        <div class="kpi-card" style="border-left-color:#dc3545;"><h3>Riesgo (Obes/Bajo)</h3><div class="value"><?php echo $porcentaje; ?>%</div></div>
                    </div>

                    <div class="charts-container">
                        <div class="chart-box" style="flex:0 0 350px;">
                            <h3>Estado Nutricional</h3>
                            <canvas id="grafico"></canvas>
                        </div>
                        <div class="chart-box">
                            <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #eee;">
                                <h3>Alertas</h3>
                                <div class="filter-tabs">
                                    <!-- ENLACES INTELIGENTES: Usamos buildUrl para no perder filtros -->
                                    <a href="<?php echo buildUrl(['ver' => 'pendientes', 'pag' => 1]); ?>" 
                                       class="<?php echo $filtro_estado === 1 ? 'active' : ''; ?>">
                                       Pendientes
                                    </a>
                                    <a href="<?php echo buildUrl(['ver' => 'todos', 'pag' => 1]); ?>" 
                                       class="<?php echo $filtro_estado === null ? 'active' : ''; ?>">
                                       Historial
                                    </a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table>
                                    <thead><tr><th>Estado</th><th>Alumno</th><th>Colegio</th><th>IMC</th><th>Diag.</th><th>Acción</th></tr></thead>
                                    <tbody>
                                        <?php while($row = $stmt_alertas->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo $row['Estado']==1 ? '<span class="status-inactive">Pendiente</span>' : '<span class="status-active">Atendida</span>'; ?></td>
                                            <td><?php echo htmlspecialchars($row['Estudiante']); ?></td>
                                            <td><small><?php echo htmlspecialchars($row['Establecimiento']); ?></small></td>
                                            <td><b><?php echo $row['IMC']; ?></b></td>
                                            <td><?php echo $row['Diagnostico']; ?></td>
                                            <td class="actions">
                                                <a href="AdminDAEM/gestionar_alerta.php?id=<?php echo $row['IdAlerta']; ?>" class="btn-action btn-edit"><i class="fa-solid fa-file-pen"></i></a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if($stmt_alertas->rowCount() == 0): ?>
                                            <tr><td colspan="6" style="text-align:center; color:#999; padding:20px;">Sin registros.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación Inteligente -->
                            <?php if ($total_pags > 1): ?>
                            <div class="pagination">
                                <?php for($i=1; $i<=$total_pags; $i++): ?>
                                    <a href="<?php echo buildUrl(['pag' => $i]); ?>" 
                                       class="page-link <?php echo $i==$pagina_actual?'active':''; ?>">
                                       <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script>
        new Chart(document.getElementById('grafico'), {
            type: 'doughnut',
            data: { labels: <?php echo json_encode($labels); ?>, datasets: [{ data: <?php echo json_encode($data); ?>, backgroundColor: <?php echo json_encode($colores); ?>, borderWidth: 1 }] },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    </script>
</body>
</html>