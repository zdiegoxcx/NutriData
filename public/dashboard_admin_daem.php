<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN DE SEGURIDAD ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorDAEM') {
    header("Location: login.php");
    exit;
}

// --- LÓGICA DE DATOS Y ESTADÍSTICAS ---

// 1. Obtener Totales Generales (KPIs)
// Usamos la última medición de cada estudiante para tener el dato más actual
$sql_kpi = "
    SELECT 
        COUNT(*) as total_mediciones,
        AVG(r.IMC) as promedio_imc,
        SUM(CASE WHEN r.IMC < 18.5 OR r.IMC >= 25 THEN 1 ELSE 0 END) as casos_riesgo
    FROM RegistroNutricional r
    INNER JOIN (
        SELECT Id_Estudiante, MAX(FechaMedicion) as MaxFecha
        FROM RegistroNutricional
        GROUP BY Id_Estudiante
    ) ultimos ON r.Id_Estudiante = ultimos.Id_Estudiante AND r.FechaMedicion = ultimos.MaxFecha
";
$stmt_kpi = $pdo->query($sql_kpi);
$kpis = $stmt_kpi->fetch(PDO::FETCH_ASSOC);

// Evitar división por cero
$porcentaje_riesgo = ($kpis['total_mediciones'] > 0) 
    ? round(($kpis['casos_riesgo'] / $kpis['total_mediciones']) * 100, 1) 
    : 0;


// 2. Obtener Datos para Gráfico de Distribución (Estado Nutricional)
// Clasificación simplificada: Bajo Peso (<18.5), Normal (18.5-24.9), Sobrepeso (25-29.9), Obesidad (>=30)
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
    INNER JOIN (
        SELECT Id_Estudiante, MAX(FechaMedicion) as MaxFecha
        FROM RegistroNutricional
        GROUP BY Id_Estudiante
    ) ultimos ON r.Id_Estudiante = ultimos.Id_Estudiante AND r.FechaMedicion = ultimos.MaxFecha
    GROUP BY estado
";
$stmt_grafico = $pdo->query($sql_grafico);
$datos_grafico = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

// Preparar arrays para Chart.js
$labels = [];
$data = [];
$colores = [];

foreach ($datos_grafico as $fila) {
    $labels[] = $fila['estado'];
    $data[] = $fila['cantidad'];
    
    // Asignar colores según estado
    switch($fila['estado']) {
        case 'Bajo Peso': $colores[] = '#ffc107'; break; // Amarillo
        case 'Normal': $colores[] = '#198754'; break;    // Verde
        case 'Sobrepeso': $colores[] = '#fd7e14'; break; // Naranja
        case 'Obesidad': $colores[] = '#dc3545'; break;  // Rojo
    }
}

// 3. Tabla de Alertas (Últimos casos de riesgo detectados)
$sql_alertas = "
    SELECT 
        e.Nombre as Estudiante,
        e.Rut,
        c.Nombre as Curso,
        est.Nombre as Establecimiento,
        r.IMC,
        r.FechaMedicion
    FROM RegistroNutricional r
    JOIN Estudiante e ON r.Id_Estudiante = e.Id
    JOIN Curso c ON e.Id_Curso = c.Id
    JOIN Establecimiento est ON c.Id_Establecimiento = est.Id
    WHERE r.IMC < 18.5 OR r.IMC >= 25
    ORDER BY r.FechaMedicion DESC
    LIMIT 10
";
$stmt_alertas = $pdo->query($sql_alertas);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard DAEM - NutriMonitor</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos específicos para este dashboard */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #0d6efd;
        }
        .kpi-card h3 { font-size: 0.9rem; color: #666; margin-bottom: 10px; }
        .kpi-card .value { font-size: 2rem; font-weight: bold; color: #333; }
        .kpi-card .subtext { font-size: 0.8rem; color: #999; }
        
        .charts-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .chart-box {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .alert-row-danger { background-color: #fff5f5; }
        .alert-row-warning { background-color: #fff9db; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DAEM NutriMonitor</h2>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-category">Reportes</div>
                <a href="dashboard_admin_daem.php" class="nav-item active">
                    <i class="fa-solid fa-chart-pie"></i> Panorama General
                </a>
                <a href="#" class="nav-item" style="opacity: 0.5; cursor: not-allowed;" title="Próximamente">
                    <i class="fa-solid fa-file-pdf"></i> Exportar Informes
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user">
                    <?php echo htmlspecialchars($_SESSION['user_nombre']); ?> (DAEM)
                </div>
                <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
            </header>

            <section class="content-body">
                <div class="content-container" style="background: transparent; box-shadow: none; padding: 0;">
                    
                    <div class="content-header-with-btn">
                        <h1><i class="fa-solid fa-chart-line"></i> Estado Nutricional Comunal</h1>
                        </div>

                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <h3>Total Estudiantes Medidos</h3>
                            <div class="value"><?php echo number_format($kpis['total_mediciones']); ?></div>
                            <div class="subtext">Datos actualizados</div>
                        </div>
                        <div class="kpi-card" style="border-left-color: #198754;">
                            <h3>Promedio IMC Comunal</h3>
                            <div class="value"><?php echo number_format($kpis['promedio_imc'], 1); ?></div>
                            <div class="subtext">Índice global</div>
                        </div>
                        <div class="kpi-card" style="border-left-color: #dc3545;">
                            <h3>Estudiantes en Riesgo</h3>
                            <div class="value"><?php echo $porcentaje_riesgo; ?>%</div>
                            <div class="subtext"><?php echo $kpis['casos_riesgo']; ?> casos detectados</div>
                        </div>
                    </div>

                    <div class="charts-container">
                        <div class="chart-box">
                            <h3>Distribución del Estado Nutricional</h3>
                            <canvas id="graficoNutricional"></canvas>
                        </div>
                        <div class="chart-box">
                            <h3>Alertas Recientes (Últimos 10)</h3>
                            <div class="table-responsive" style="margin-top: 15px;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Estudiante</th>
                                            <th>Establecimiento</th>
                                            <th>IMC</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($alerta = $stmt_alertas->fetch(PDO::FETCH_ASSOC)): 
                                            $clase_alerta = ($alerta['IMC'] >= 30) ? 'status-inactive' : 'status-active'; // Solo visual
                                            $color_imc = ($alerta['IMC'] >= 30 || $alerta['IMC'] < 16) ? '#dc3545' : '#fd7e14';
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($alerta['Estudiante']); ?></td>
                                            <td><?php echo htmlspecialchars($alerta['Establecimiento']); ?></td>
                                            <td style="font-weight: bold; color: <?php echo $color_imc; ?>">
                                                <?php echo htmlspecialchars($alerta['IMC']); ?>
                                            </td>
                                            <td><?php echo date("d/m/Y", strtotime($alerta['FechaMedicion'])); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if($stmt_alertas->rowCount() == 0): ?>
                                            <tr><td colspan="4" style="text-align:center;">No hay alertas recientes.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('graficoNutricional').getContext('2d');
        const graficoNutricional = new Chart(ctx, {
            type: 'doughnut', // Gráfico de dona
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Estudiantes',
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: <?php echo json_encode($colores); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>
</html>