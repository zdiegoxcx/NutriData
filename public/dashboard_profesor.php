<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN DE SEGURIDAD ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'profesor') {
    header("Location: login.php");
    exit;
}

// Recibir parámetros de la URL
$vista = $_GET['vista'] ?? 'cursos';
$id_curso = $_GET['id_curso'] ?? null;
$id_estudiante = $_GET['id_estudiante'] ?? null;

// --- CONFIGURACIÓN DE PAGINACIÓN ---
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// --- FUNCIÓN HELPER PARA URLS ---
function buildUrl($params = []) {
    $currentParams = $_GET;
    $merged = array_merge($currentParams, $params);
    return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docente - NutriData</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 25px; flex-wrap: wrap; }
        .page-link { padding: 8px 14px; border: 1px solid #ddd; background: white; text-decoration: none; border-radius: 4px; color: #333; font-weight: 500; transition: all 0.2s; }
        .page-link:hover { background-color: #f8f9fa; }
        .page-link.active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
        .page-info { text-align: center; margin-top: 10px; color: #888; font-size: 0.85rem; }
        .clickable-row { cursor: pointer; transition: background-color 0.2s ease; }
        .clickable-row:hover { background-color: #f1f5f9; }
        .clickable-row td { vertical-align: middle; }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-left">
            <button class="btn-toggle-menu" onclick="toggleSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="brand-logo">
                <i class="fa-solid fa-apple-whole"></i> NutriData <span style="font-weight:400; color:#666; font-size:1rem; margin-left:5px;">| Docente</span>
            </div>
        </div>
        <div class="header-user-section">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></span>
                <span class="user-role">Profesor Encargado</span>
            </div>
            <a href="logout.php" class="btn-logout" title="Cerrar Sesión"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar" id="mainSidebar">
        <div class="sidebar-header">
            <h3 style="color:var(--primary-color); font-size:1.1rem; margin:0;">Navegación</h3>
            <button onclick="toggleSidebar()" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:#666;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-category">Principal</div>
            <a href="dashboard_profesor.php?vista=cursos" class="nav-item <?php echo ($vista == 'cursos' || $vista == 'estudiantes' || $vista == 'mediciones') ? 'active' : ''; ?>">
               <i class="fa-solid fa-chalkboard-user"></i> Mis Cursos Asignados
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="mensaje error"><i class="fa-solid fa-triangle-exclamation"></i> '.$_SESSION['error'].'</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="mensaje success"><i class="fa-solid fa-check-circle"></i> '.$_SESSION['success'].'</div>';
            unset($_SESSION['success']);
        }
        ?>

        <div class="content-container">
            <?php
            // ===========================================================
            //                     VISTA 1: MIS CURSOS
            // ===========================================================
            if ($vista === 'cursos') {

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-chalkboard'></i> Mis Cursos Asignados</h1>";
                echo '</div>';

                // 1. Contar Total Cursos
                $sql_count = "SELECT COUNT(*) FROM Curso WHERE Id_Profesor = ? AND Estado = 1";
                $stmt_c = $pdo->prepare($sql_count);
                $stmt_c->execute([$_SESSION['user_id']]);
                $total_regs = $stmt_c->fetchColumn();
                $total_pags = ceil($total_regs / $registros_por_pagina);

                // 2. Obtener Cursos Paginados
                $stmt = $pdo->prepare("
                    SELECT c.Id, c.Nombre, e.Nombre AS Establecimiento
                    FROM Curso c
                    JOIN Establecimiento e ON c.Id_Establecimiento = e.Id
                    WHERE c.Id_Profesor = ? AND c.Estado = 1
                    ORDER BY c.Nombre
                    LIMIT $registros_por_pagina OFFSET $offset
                ");
                $stmt->execute([$_SESSION['user_id']]);

                echo "<div class='table-responsive'>
                      <table>
                      <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Establecimiento</th>
                        </tr>
                      </thead>
                      <tbody>";

                $encontrados = false;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $encontrados = true;
                    $url = "dashboard_profesor.php?vista=estudiantes&id_curso=" . $row['Id'];
                    echo "<tr class='clickable-row' onclick=\"window.location='$url'\">
                          <td><strong>".htmlspecialchars($row['Nombre'])."</strong></td>
                          <td>".htmlspecialchars($row['Establecimiento'])."</td>
                          </tr>";
                }
                
                if (!$encontrados) {
                    echo "<tr><td colspan='2' style='text-align:center; padding:20px;'>No tienes cursos asignados.</td></tr>";
                }

                echo "</tbody></table></div>";

                if ($total_pags > 1) {
                    echo '<div class="pagination">';
                    $rango = 2; $actual = $pagina_actual; $total = $total_pags; $param = 'pag';
                    if ($actual > 1) echo '<a href="'.buildUrl([$param => $actual - 1]).'" class="page-link">&laquo;</a>';
                    if ($actual > ($rango + 1)) { echo '<a href="'.buildUrl([$param => 1]).'" class="page-link">1</a>'; if ($actual > ($rango + 2)) echo '<span style="padding:0 5px; color:#666;">...</span>'; }
                    for ($i = max(1, $actual - $rango); $i <= min($total, $actual + $rango); $i++) { $active = ($i == $actual) ? 'active' : ''; echo '<a href="'.buildUrl([$param => $i]).'" class="page-link '.$active.'">'.$i.'</a>'; }
                    if ($actual < ($total - $rango)) { if ($actual < ($total - $rango - 1)) echo '<span style="padding:0 5px; color:#666;">...</span>'; echo '<a href="'.buildUrl([$param => $total]).'" class="page-link">'.$total.'</a>'; }
                    if ($actual < $total) echo '<a href="'.buildUrl([$param => $actual + 1]).'" class="page-link">&raquo;</a>';
                    echo '</div><div class="page-info">Página '.$actual.' de '.$total.'</div>';
                }
            }

            // ===========================================================
            //            VISTA 2: ESTUDIANTES DEL CURSO
            // ===========================================================
            elseif ($vista === 'estudiantes' && $id_curso) {

                $stmt_curso = $pdo->prepare("SELECT Nombre FROM Curso WHERE Id = ?");
                $stmt_curso->execute([$id_curso]);
                $nombre_curso = $stmt_curso->fetchColumn();

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-children'></i> Curso: $nombre_curso</h1>";
                echo '<a href="dashboard_profesor.php?vista=cursos" class="btn-create" style="background:#6c757d;"><i class="fa-solid fa-arrow-left"></i> Volver a Cursos</a>';
                echo "</div>";

                $sql_count = "SELECT COUNT(*) FROM Estudiante WHERE Id_Curso = ? AND Estado = 1";
                $stmt_c = $pdo->prepare($sql_count);
                $stmt_c->execute([$id_curso]);
                $total_regs = $stmt_c->fetchColumn();
                $total_pags = ceil($total_regs / $registros_por_pagina);

                $stmt = $pdo->prepare("
                    SELECT Id, Rut, Nombres, ApellidoPaterno, ApellidoMaterno
                    FROM Estudiante
                    WHERE Id_Curso = ? AND Estado = 1
                    ORDER BY ApellidoPaterno, ApellidoMaterno, Nombres
                    LIMIT $registros_por_pagina OFFSET $offset
                ");
                $stmt->execute([$id_curso]);

                echo "<div class='table-responsive'>
                      <table>
                      <thead>
                        <tr>
                            <th>RUT</th>
                            <th>Nombre Completo</th>
                        </tr>
                      </thead>
                      <tbody>";

                $encontrados = false;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $encontrados = true;
                    $nombreFull = $row['Nombres'] . ' ' . $row['ApellidoPaterno'] . ' ' . ($row['ApellidoMaterno'] ?? '');
                    $url = "dashboard_profesor.php?vista=mediciones&id_estudiante=" . $row['Id'];

                    echo "<tr class='clickable-row' onclick=\"window.location='$url'\">
                          <td>".htmlspecialchars($row['Rut'])."</td>
                          <td>".htmlspecialchars($nombreFull)."</td>
                          </tr>";
                }
                if (!$encontrados) {
                    echo "<tr><td colspan='2' style='text-align:center; padding:20px;'>No hay estudiantes en este curso.</td></tr>";
                }
                echo "</tbody></table></div>";

                if ($total_pags > 1) {
                    echo '<div class="pagination">';
                    $rango = 2; $actual = $pagina_actual; $total = $total_pags; $param = 'pag';
                    if ($actual > 1) echo '<a href="'.buildUrl([$param => $actual - 1]).'" class="page-link">&laquo;</a>';
                    if ($actual > ($rango + 1)) { echo '<a href="'.buildUrl([$param => 1]).'" class="page-link">1</a>'; if ($actual > ($rango + 2)) echo '<span style="padding:0 5px; color:#666;">...</span>'; }
                    for ($i = max(1, $actual - $rango); $i <= min($total, $actual + $rango); $i++) { $active = ($i == $actual) ? 'active' : ''; echo '<a href="'.buildUrl([$param => $i]).'" class="page-link '.$active.'">'.$i.'</a>'; }
                    if ($actual < ($total - $rango)) { if ($actual < ($total - $rango - 1)) echo '<span style="padding:0 5px; color:#666;">...</span>'; echo '<a href="'.buildUrl([$param => $total]).'" class="page-link">'.$total.'</a>'; }
                    if ($actual < $total) echo '<a href="'.buildUrl([$param => $actual + 1]).'" class="page-link">&raquo;</a>';
                    echo '</div><div class="page-info">Página '.$actual.' de '.$total.'</div>';
                }
            }

            // ===========================================================
            //           VISTA 3: MEDICIONES DEL ESTUDIANTE
            // ===========================================================
            elseif ($vista === 'mediciones' && $id_estudiante) {

                $stmt_est = $pdo->prepare("SELECT Nombres, ApellidoPaterno, Id_Curso FROM Estudiante WHERE Id = ?");
                $stmt_est->execute([$id_estudiante]);
                $est = $stmt_est->fetch(PDO::FETCH_ASSOC);
                
                if ($est) {
                    $nombre_est = $est['Nombres'] . " " . $est['ApellidoPaterno'];
                    $id_curso_volver = $est['Id_Curso'];
                } else {
                    die("Estudiante no encontrado");
                }

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-notes-medical'></i> Historial: $nombre_est</h1>";
                echo "<div style='display:flex; gap:10px;'>
                        <a href='dashboard_profesor.php?vista=estudiantes&id_curso=$id_curso_volver' class='btn-create' style='background:#6c757d;'><i class='fa-solid fa-arrow-left'></i> Volver</a>
                        <a href='registrar_medicion.php?id_estudiante=$id_estudiante' class='btn-create'><i class='fa-solid fa-plus'></i> Nueva Medición</a>
                      </div>";
                echo '</div>';

                $sql_count = "SELECT COUNT(*) FROM RegistroNutricional WHERE Id_Estudiante = ?";
                $stmt_c = $pdo->prepare($sql_count);
                $stmt_c->execute([$id_estudiante]);
                $total_regs = $stmt_c->fetchColumn();
                $total_pags = ceil($total_regs / $registros_por_pagina);

                $stmt = $pdo->prepare("
                    SELECT FechaMedicion as Fecha, Peso, Altura, IMC, Diagnostico, Observaciones
                    FROM RegistroNutricional
                    WHERE Id_Estudiante = ?
                    ORDER BY FechaMedicion DESC
                    LIMIT $registros_por_pagina OFFSET $offset
                ");
                $stmt->execute([$id_estudiante]);

                echo "<div class='table-responsive'>
                      <table>
                      <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Peso (KG)</th>
                            <th>Altura (M)</th>
                            <th>IMC</th>
                            <th>Diagnóstico</th>
                            <th>Observaciones</th>
                        </tr>
                      </thead>
                      <tbody>";

                $encontrados = false;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $encontrados = true;
                    $d = $row['Diagnostico'];
                    $c = '#333';
                    if(strpos($d,'Bajo')!==false) $c='#ffc107';      
                    elseif(strpos($d,'Normal')!==false) $c='#198754'; 
                    elseif(strpos($d,'Sobrepeso')!==false) $c='#fd7e14'; 
                    elseif(strpos($d,'Obesidad')!==false) $c='#dc3545';  

                    echo "<tr>
                            <td>" . date("d/m/Y", strtotime($row['Fecha'])) . "</td>
                            <td>" . htmlspecialchars($row['Peso']) . "</td>
                            <td>" . htmlspecialchars($row['Altura']) . "</td>
                            <td><strong>" . htmlspecialchars($row['IMC']) . "</strong></td>
                            <td style='color:$c; font-weight:bold;'>" . htmlspecialchars($d) . "</td>
                            <td>" . htmlspecialchars($row['Observaciones']) . "</td>
                          </tr>";
                }
                if (!$encontrados) {
                    echo "<tr><td colspan='6' style='text-align:center; padding:20px;'>No hay mediciones registradas aún.</td></tr>";
                }
                echo "</tbody></table></div>";

                if ($total_pags > 1) {
                    echo '<div class="pagination">';
                    $rango = 2; $actual = $pagina_actual; $total = $total_pags; $param = 'pag';
                    if ($actual > 1) echo '<a href="'.buildUrl([$param => $actual - 1]).'" class="page-link">&laquo;</a>';
                    if ($actual > ($rango + 1)) { echo '<a href="'.buildUrl([$param => 1]).'" class="page-link">1</a>'; if ($actual > ($rango + 2)) echo '<span style="padding:0 5px; color:#666;">...</span>'; }
                    for ($i = max(1, $actual - $rango); $i <= min($total, $actual + $rango); $i++) { $active = ($i == $actual) ? 'active' : ''; echo '<a href="'.buildUrl([$param => $i]).'" class="page-link '.$active.'">'.$i.'</a>'; }
                    if ($actual < ($total - $rango)) { if ($actual < ($total - $rango - 1)) echo '<span style="padding:0 5px; color:#666;">...</span>'; echo '<a href="'.buildUrl([$param => $total]).'" class="page-link">'.$total.'</a>'; }
                    if ($actual < $total) echo '<a href="'.buildUrl([$param => $actual + 1]).'" class="page-link">&raquo;</a>';
                    echo '</div><div class="page-info">Página '.$actual.' de '.$total.'</div>';
                }
            }
            ?>
        </div>
        
        <footer class="main-footer">
            &copy; <?php echo date("Y"); ?> NutriData - DAEM.
        </footer>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('mainSidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>