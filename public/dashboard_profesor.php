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

// --- CONFIGURACIÓN DE PAGINACIÓN (20 por página) ---
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// --- FUNCIÓN HELPER PARA URLS ---
// Mantiene los filtros actuales (id_curso, id_estudiante, vista) al cambiar de página
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
    <title>Dashboard Profesor - NutriData</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos Paginación */
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
        .page-link.active { background: #4361ee; color: white; border-color: #4361ee; }
        .page-info { text-align: center; margin-top: 10px; color: #888; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="dashboard-wrapper">

    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>NutriData</h2>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-category">Profesor</div>
            <a href="dashboard_profesor.php?vista=cursos"
               class="nav-item <?= ($vista == 'cursos' || $vista == 'estudiantes' || $vista == 'mediciones') ? 'active' : '' ?>">
               <i class="fa-solid fa-chalkboard-user"></i> Mis Cursos
            </a>
        </nav>
    </aside>

    <main class="main-content">

        <header class="header">
            <div class="header-user">
                <?= htmlspecialchars($_SESSION['user_nombre']); ?>
            </div>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </header>

        <section class="content-body">
            <div class="content-container">

            <?php
            // MENSAJES DE ERROR O ÉXITO
            if (isset($_SESSION['error'])) {
                echo '<div class="mensaje error">'.$_SESSION['error'].'</div>';
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="mensaje success">'.$_SESSION['success'].'</div>';
                unset($_SESSION['success']);
            }
            ?>

            <?php
            // ===========================================================
            //                     VISTA 1: MIS CURSOS
            // ===========================================================
            if ($vista === 'cursos') {

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-chalkboard'></i> Mis Cursos Asignados</h1>";
                echo '</div>';

                // 1. Contar Total Cursos (Para Paginación)
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
                            <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>";

                $encontrados = false;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $encontrados = true;
                    echo "<tr>
                          <td><strong>".htmlspecialchars($row['Nombre'])."</strong></td>
                          <td>".htmlspecialchars($row['Establecimiento'])."</td>
                          <td class='actions'>
                            <a class='btn-action btn-view' title='Ver Estudiantes'
                               href='dashboard_profesor.php?vista=estudiantes&id_curso=".$row['Id']."'>
                               <i class='fa-solid fa-users'></i>
                            </a>
                          </td>
                          </tr>";
                }
                
                if (!$encontrados) {
                    echo "<tr><td colspan='3' style='text-align:center; padding:20px;'>No tienes cursos asignados.</td></tr>";
                }

                echo "</tbody></table></div>";

                // Paginador
                if ($total_pags > 1) {
                    echo '<div class="pagination">';
                    for ($i=1; $i<=$total_pags; $i++) {
                        echo '<a href="'.buildUrl(['pag' => $i]).'" class="page-link '.($i==$pagina_actual?'active':'').'">'.$i.'</a>';
                    }
                    echo '</div><div class="page-info">Página '.$pagina_actual.' de '.$total_pags.'</div>';
                }
            }


            // ===========================================================
            //            VISTA 2: ESTUDIANTES DEL CURSO
            // ===========================================================
            elseif ($vista === 'estudiantes' && $id_curso) {

                // Obtener nombre del curso
                $stmt_curso = $pdo->prepare("SELECT Nombre FROM Curso WHERE Id = ?");
                $stmt_curso->execute([$id_curso]);
                $nombre_curso = $stmt_curso->fetchColumn();

                echo '<div class="content-header-with-btn">';
                echo "<h1><i class='fa-solid fa-children'></i> Curso: $nombre_curso</h1>";
                echo '<a href="dashboard_profesor.php?vista=cursos" class="btn-create" style="background:#6c757d;"><i class="fa-solid fa-arrow-left"></i> Volver a Cursos</a>';
                echo "</div>";

                // 1. Contar Total Estudiantes
                $sql_count = "SELECT COUNT(*) FROM Estudiante WHERE Id_Curso = ? AND Estado = 1";
                $stmt_c = $pdo->prepare($sql_count);
                $stmt_c->execute([$id_curso]);
                $total_regs = $stmt_c->fetchColumn();
                $total_pags = ceil($total_regs / $registros_por_pagina);

                // 2. Obtener Estudiantes Paginados
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
                            <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>";

                $encontrados = false;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $encontrados = true;
                    // Concatenación segura
                    $nombreFull = $row['Nombres'] . ' ' . $row['ApellidoPaterno'] . ' ' . ($row['ApellidoMaterno'] ?? '');
                    
                    echo "<tr>
                          <td>".htmlspecialchars($row['Rut'])."</td>
                          <td>".htmlspecialchars($nombreFull)."</td>
                          <td class='actions'>
                            <a class='btn-action btn-view' title='Ver Historial y Medir'
                               href='dashboard_profesor.php?vista=mediciones&id_estudiante=".$row['Id']."'>
                               <i class='fa-solid fa-notes-medical'></i>
                            </a>
                          </td>
                          </tr>";
                }
                if (!$encontrados) {
                    echo "<tr><td colspan='3' style='text-align:center; padding:20px;'>No hay estudiantes en este curso.</td></tr>";
                }
                echo "</tbody></table></div>";

                // Paginador
                if ($total_pags > 1) {
                    echo '<div class="pagination">';
                    for ($i=1; $i<=$total_pags; $i++) {
                        echo '<a href="'.buildUrl(['pag' => $i]).'" class="page-link '.($i==$pagina_actual?'active':'').'">'.$i.'</a>';
                    }
                    echo '</div><div class="page-info">Página '.$pagina_actual.' de '.$total_pags.'</div>';
                }
            }


            // ===========================================================
            //           VISTA 3: MEDICIONES DEL ESTUDIANTE
            // ===========================================================
            elseif ($vista === 'mediciones' && $id_estudiante) {

                // Obtener datos del estudiante para título y botón volver
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

                // 1. Contar Total Mediciones
                $sql_count = "SELECT COUNT(*) FROM RegistroNutricional WHERE Id_Estudiante = ?";
                $stmt_c = $pdo->prepare($sql_count);
                $stmt_c->execute([$id_estudiante]);
                $total_regs = $stmt_c->fetchColumn();
                $total_pags = ceil($total_regs / $registros_por_pagina);

                // 2. Obtener Mediciones Paginadas
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
                    // Colores diagnóstico
                    $d = $row['Diagnostico'];
                    $c = '#333';
                    if(strpos($d,'Bajo')!==false) $c='#ffc107';      // Amarillo
                    elseif(strpos($d,'Normal')!==false) $c='#198754'; // Verde
                    elseif(strpos($d,'Sobrepeso')!==false) $c='#fd7e14'; // Naranja
                    elseif(strpos($d,'Obesidad')!==false) $c='#dc3545';  // Rojo

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

                // Paginador
                if ($total_pags > 1) {
                    echo '<div class="pagination">';
                    for ($i=1; $i<=$total_pags; $i++) {
                        echo '<a href="'.buildUrl(['pag' => $i]).'" class="page-link '.($i==$pagina_actual?'active':'').'">'.$i.'</a>';
                    }
                    echo '</div><div class="page-info">Página '.$pagina_actual.' de '.$total_pags.'</div>';
                }
            }
            ?>

            </div>
        </section>
        <footer class="main-footer">
                &copy; <?php echo date("Y"); ?> <strong>NutriData</strong> - Departamento de Administración de Educación Municipal (DAEM).
        </footer>
    </main>
</div>

</body>
</html>