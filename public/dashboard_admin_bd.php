<?php
session_start();
require_once __DIR__ . '/../src/config/db.php'; 
$pdo = getConnection();

// --- GUARDIÁN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: login.php");
    exit;
}

$vista = $_GET['vista'] ?? 'estudiantes'; 
$id_establecimiento = $_GET['id_establecimiento'] ?? null;
$id_curso = $_GET['id_curso'] ?? null;

// --- CONFIGURACIÓN DE PAGINACIÓN ---
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// --- HELPER PARA URLS (Mantiene filtros al paginar) ---
function buildUrl($params = []) {
    $currentParams = $_GET;
    $merged = array_merge($currentParams, $params);
    return '?' . http_build_query($merged);
}

// --- VALIDACIÓN DE SEGURIDAD (Evitar ver cosas borradas) ---
if ($id_establecimiento) {
    $stmt = $pdo->prepare("SELECT Id FROM Establecimiento WHERE Id = ? AND Estado = 1");
    $stmt->execute([$id_establecimiento]);
    if (!$stmt->fetch()) { header("Location: dashboard_admin_bd.php"); exit; }
}
if ($id_curso) {
    $stmt = $pdo->prepare("SELECT Id FROM Curso WHERE Id = ? AND Estado = 1");
    $stmt->execute([$id_curso]);
    if (!$stmt->fetch()) { header("Location: dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_establecimiento"); exit; }
}

// ======================================================================================
// LÓGICA DE ACCIONES (ELIMINAR / REACTIVAR)
// ======================================================================================
if (isset($_GET['action'])) {
    $id = $_GET['id'] ?? null;
    $accion = $_GET['action'];

    if ($id) {
        try {
            $pdo->beginTransaction();

            if ($accion == 'eliminar') {
                $tipo = $_GET['tipo'] ?? '';
                $motivo = $_GET['motivo'] ?? 'Sin motivo especificado.';

                if ($tipo == 'establecimiento') {
                    $pdo->prepare("UPDATE Establecimiento SET Estado = 0, FechaEliminacion = NOW() WHERE Id = ?")->execute([$id]);
                    $pdo->prepare("UPDATE Curso SET Estado = 0, FechaEliminacion = NOW() WHERE Id_Establecimiento = ?")->execute([$id]);
                    $msg = "Eliminación en cascada: Se eliminó el Establecimiento.";
                    $pdo->prepare("UPDATE Estudiante e INNER JOIN Curso c ON e.Id_Curso = c.Id SET e.Estado = 0, e.FechaEliminacion = NOW(), e.MotivoEliminacion = ? WHERE c.Id_Establecimiento = ?")->execute([$msg, $id]);
                    $_SESSION['success_message'] = "Establecimiento eliminado.";

                } elseif ($tipo == 'curso') {
                    $pdo->prepare("UPDATE Curso SET Estado = 0, FechaEliminacion = NOW() WHERE Id = ?")->execute([$id]);
                    $msg = "Eliminación en cascada: Se eliminó el Curso.";
                    $pdo->prepare("UPDATE Estudiante SET Estado = 0, FechaEliminacion = NOW(), MotivoEliminacion = ? WHERE Id_Curso = ?")->execute([$msg, $id]);
                    $_SESSION['success_message'] = "Curso eliminado.";

                } elseif ($tipo == 'estudiante') {
                    $pdo->prepare("UPDATE Estudiante SET Estado = 0, FechaEliminacion = NOW(), MotivoEliminacion = ? WHERE Id = ?")->execute([$motivo, $id]);
                    $_SESSION['success_message'] = "Estudiante eliminado.";
                
                } elseif ($tipo == 'usuario') {
                    $pdo->prepare("UPDATE Usuario SET Estado = 0, FechaEliminacion = NOW(), MotivoEliminacion = ? WHERE Id = ?")->execute([$motivo, $id]);
                    $_SESSION['success_message'] = "Usuario desactivado.";
                }
            } elseif ($accion == 'reactivar') {
                $pdo->prepare("UPDATE Usuario SET Estado = 1, FechaEliminacion = NULL, MotivoEliminacion = NULL WHERE Id = ?")->execute([$id]);
                $_SESSION['success_message'] = "Usuario reactivado.";
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        // Redirección limpia manteniendo filtros
        $redirect = 'dashboard_admin_bd.php?vista=' . $vista;
        if ($id_establecimiento) $redirect .= '&id_establecimiento=' . $id_establecimiento;
        if ($id_curso) $redirect .= '&id_curso=' . $id_curso;
        header("Location: " . $redirect);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin BD</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/advertencia.css">
    <link rel="stylesheet" href="css/desplegable.css">
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
        .page-link.active { background: #0d6efd; color: white; border-color: #0d6efd; }
        .page-info { text-align: center; margin-top: 10px; color: #888; font-size: 0.85rem; }
    </style>
</head>
<body>
    
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                <div class="nav-category">Principal</div>
                <a href="dashboard_admin_bd.php?vista=estudiantes" class="nav-item <?php echo ($vista == 'estudiantes') ? 'active' : ''; ?>"><i class="fa-solid fa-school"></i> Establecimientos</a>
                <div class="nav-category">Administración</div>
                <a href="dashboard_admin_bd.php?vista=usuarios" class="nav-item <?php echo ($vista == 'usuarios') ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i> Usuarios</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></div>
                <a href="logout.php" class="btn-logout">Cerrar Sesion</a>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <?php
                    if (isset($_SESSION['success_message'])) { echo '<div class="mensaje success">' . $_SESSION['success_message'] . '</div>'; unset($_SESSION['success_message']); }
                    if (isset($_SESSION['error'])) { echo '<div class="mensaje error">' . $_SESSION['error'] . '</div>'; unset($_SESSION['error']); }
                    
                    // =================================================================================
                    // VISTA 1: GESTIÓN DE USUARIOS
                    // =================================================================================
                    if ($vista === 'usuarios') {
                        echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-users"></i> Gestión de Usuarios</h1><a href="AdminBD/crud_usuario/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Usuario</a></div>'; 
                        
                        // 1. Contar Total
                        $total_usuarios = $pdo->query("SELECT COUNT(*) FROM Usuario")->fetchColumn();
                        $total_pags = ceil($total_usuarios / $registros_por_pagina);

                        // 2. Obtener Datos Paginados
                        $stmt = $pdo->prepare("SELECT u.Id, u.Rut, u.Nombre, u.Apellido, u.Email, u.Estado, r.Nombre AS NombreRol FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id ORDER BY u.Apellido, u.Nombre LIMIT $registros_por_pagina OFFSET $offset");
                        $stmt->execute();

                        echo "<div class='table-responsive'><table><thead><tr><th>RUT</th><th>Nombre Completo</th><th>Email</th><th>Rol</th><th>Estado</th><th></th></tr></thead><tbody>";
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $es_activo = ($row['Estado'] == 1);
                            $estado_html = $es_activo ? '<span class="status-active">Activo</span>' : '<span class="status-inactive">Inactivo</span>';
                            echo "<tr class='clickable-row'><td>".htmlspecialchars($row['Rut'])."</td><td>".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."</td><td>".htmlspecialchars($row['Email'])."</td><td>".htmlspecialchars($row['NombreRol'])."</td><td>$estado_html</td><td class='menu-column'><div class='dropdown'><button class='btn-dots' onclick=\"toggleMenu(event, 'u".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button><div id='menu-u".$row['Id']."' class='dropdown-menu'>";
                            if ($es_activo) {
                                echo "<a href='AdminBD/crud_usuario/edit.php?id=".$row['Id']."'><i class='fa-solid fa-pencil'></i> Editar</a><a href='javascript:void(0);' class='danger-action' onclick=\"openDeleteModal('usuario', ".$row['Id'].", '".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."')\"><i class='fa-solid fa-ban'></i> Desactivar</a>";
                            } else {
                                echo "<a href='AdminBD/crud_usuario/edit.php?id=".$row['Id']."'><i class='fa-solid fa-eye'></i> Ver Detalle</a><a href='javascript:void(0);' class='success-action' onclick=\"confirmReactivate(".$row['Id'].", '".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."')\"><i class='fa-solid fa-rotate-left'></i> Reactivar</a>";
                            }
                            echo "</div></div></td></tr>";
                        }
                        echo "</tbody></table></div>";

                        // Paginador Inteligente
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

                    } else {
                        // BREADCRUMBS
                        echo '<nav class="breadcrumbs"><a href="dashboard_admin_bd.php?vista=estudiantes" class="'.(!$id_establecimiento ? 'active' : '').'">Establecimientos</a>';
                        if ($id_establecimiento) {
                            $stmt = $pdo->prepare("SELECT Nombre FROM Establecimiento WHERE Id = ?"); $stmt->execute([$id_establecimiento]);
                            echo '<span>></span> <a href="dashboard_admin_bd.php?vista=estudiantes&id_establecimiento='.$id_establecimiento.'" class="'.($id_establecimiento && !$id_curso ? 'active' : '').'">' . htmlspecialchars($stmt->fetchColumn()) . '</a>';
                        }
                        if ($id_curso) {
                            $stmt = $pdo->prepare("SELECT Nombre FROM Curso WHERE Id = ?"); $stmt->execute([$id_curso]);
                            echo '<span>></span> <span class="active">' . htmlspecialchars($stmt->fetchColumn()) . '</span>';
                        }
                        echo '</nav>';

                        // =================================================================================
                        // VISTA 2: LISTA DE ESTUDIANTES (DENTRO DE UN CURSO)
                        // =================================================================================
                        if ($id_establecimiento && $id_curso) {
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-children"></i> Estudiantes</h1><a href="AdminBD/crud_estudiante/create.php?id_curso='.$id_curso.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear</a></div>';
                            
                            // 1. Contar
                            $total_est = $pdo->prepare("SELECT COUNT(*) FROM Estudiante WHERE Id_Curso = ? AND Estado = 1");
                            $total_est->execute([$id_curso]);
                            $total_pags = ceil($total_est->fetchColumn() / $registros_por_pagina);

                            // 2. Datos
                            $stmt = $pdo->prepare("SELECT Id, Rut, Nombres, ApellidoPaterno, ApellidoMaterno, FechaNacimiento FROM Estudiante WHERE Id_Curso = ? AND Estado = 1 ORDER BY ApellidoPaterno, ApellidoMaterno, Nombres LIMIT $registros_por_pagina OFFSET $offset");
                            $stmt->execute([$id_curso]);
                            
                            echo "<div class='table-responsive'><table><thead><tr><th>RUT</th><th>Nombre Completo</th><th>Fecha Nac.</th><th></th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $nombreCompleto = htmlspecialchars($row['Nombres'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']);
                                echo "<tr><td>".htmlspecialchars($row['Rut'])."</td><td>".$nombreCompleto."</td><td>".htmlspecialchars($row['FechaNacimiento'])."</td><td class='menu-column'><div class='dropdown'><button class='btn-dots' onclick=\"toggleMenu(event, 's".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button><div id='menu-s".$row['Id']."' class='dropdown-menu'><a href='AdminBD/crud_estudiante/edit.php?id=".$row['Id']."'><i class='fa-solid fa-pencil'></i> Editar</a><a href='javascript:void(0);' class='danger-action' onclick=\"openDeleteModal('estudiante', ".$row['Id'].", '".$nombreCompleto."', '$id_establecimiento', '$id_curso')\"><i class='fa-solid fa-trash-can'></i> Eliminar</a></div></div></td></tr>";
                            }
                            echo "</tbody></table></div>";

                            // Paginador Inteligente
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

                        // =================================================================================
                        // VISTA 3: LISTA DE CURSOS (DENTRO DE UN ESTABLECIMIENTO)
                        // =================================================================================
                        } else if ($id_establecimiento && !$id_curso) {
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-chalkboard-user"></i> Cursos</h1><a href="AdminBD/crud_curso/create.php?id_establecimiento='.$id_establecimiento.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Curso</a></div>';
                            
                            // 1. Contar
                            $total_cur = $pdo->prepare("SELECT COUNT(*) FROM Curso WHERE Id_Establecimiento = ? AND Estado = 1");
                            $total_cur->execute([$id_establecimiento]);
                            $total_pags = ceil($total_cur->fetchColumn() / $registros_por_pagina);

                            // 2. Datos
                            $stmt = $pdo->prepare("SELECT c.Id, c.Nombre, u.Nombre AS NProf, u.Apellido AS AProf FROM Curso c JOIN Usuario u ON c.Id_Profesor = u.Id WHERE c.Id_Establecimiento = ? AND c.Estado = 1 ORDER BY c.Nombre LIMIT $registros_por_pagina OFFSET $offset");
                            $stmt->execute([$id_establecimiento]);
                            
                            echo "<div class='table-responsive'><table><thead><tr><th>Curso</th><th>Profesor</th><th></th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $url = "dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_establecimiento&id_curso=".$row['Id'];
                                echo "<tr class='clickable-row' onclick=\"window.location='$url'\"><td>".htmlspecialchars($row['Nombre'])."</td><td>".htmlspecialchars($row['NProf'].' '.$row['AProf'])."</td><td class='menu-column'><div class='dropdown'><button class='btn-dots' onclick=\"toggleMenu(event, 'c".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button><div id='menu-c".$row['Id']."' class='dropdown-menu'><a href='AdminBD/crud_curso/edit.php?id=".$row['Id']."' onclick=\"event.stopPropagation();\"><i class='fa-solid fa-pencil'></i> Editar</a><a href='javascript:void(0);' class='danger-action' onclick=\"event.stopPropagation(); openDeleteModal('curso', ".$row['Id'].", '".htmlspecialchars($row['Nombre'])."', '$id_establecimiento')\"><i class='fa-solid fa-trash-can'></i> Eliminar</a></div></div></td></tr>";
                            }
                            echo "</tbody></table></div>";

                            // Paginador Inteligente
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

                        // =================================================================================
                        // VISTA 4: LISTA DE ESTABLECIMIENTOS (PRINCIPAL)
                        // =================================================================================
                        } else {
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-school"></i> Establecimientos</h1><a href="AdminBD/crud_establecimiento/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Establecimiento</a></div>';
                            
                            // 1. Contar
                            $total_est = $pdo->query("SELECT COUNT(*) FROM Establecimiento WHERE Estado = 1")->fetchColumn();
                            $total_pags = ceil($total_est / $registros_por_pagina);

                            // 2. Datos
                            $stmt = $pdo->query("SELECT e.Id, e.Nombre, d.Direccion, c.Comuna FROM Establecimiento e LEFT JOIN Direccion d ON e.Id_Direccion = d.Id LEFT JOIN Comuna c ON d.Id_Comuna = c.Id WHERE e.Estado = 1 ORDER BY e.Nombre LIMIT $registros_por_pagina OFFSET $offset");
                            
                            echo "<div class='table-responsive'><table><thead><tr><th>Establecimiento</th><th>Dirección</th><th>Comuna</th><th></th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $url = "dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=".$row['Id'];
                                echo "<tr class='clickable-row' onclick=\"window.location='$url'\"><td>".htmlspecialchars($row['Nombre'])."</td><td>".htmlspecialchars($row['Direccion'] ?? '')."</td><td>".htmlspecialchars($row['Comuna'] ?? '')."</td><td class='menu-column'><div class='dropdown'><button class='btn-dots' onclick=\"toggleMenu(event, 'e".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button><div id='menu-e".$row['Id']."' class='dropdown-menu'><a href='AdminBD/crud_establecimiento/edit.php?id=".$row['Id']."' onclick=\"event.stopPropagation();\"><i class='fa-solid fa-pencil'></i> Editar</a><a href='javascript:void(0);' class='danger-action' onclick=\"event.stopPropagation(); openDeleteModal('establecimiento', ".$row['Id'].", '".htmlspecialchars($row['Nombre'])."')\"><i class='fa-solid fa-trash-can'></i> Eliminar</a></div></div></td></tr>";
                            }
                            echo "</tbody></table></div>";

                            // Paginador Inteligente
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
                    }
                    ?>
                </div>
            </section>
            <footer class="main-footer">
                &copy; <?php echo date("Y"); ?> <strong>NutriData</strong> - Departamento de Administración de Educación Municipal (DAEM).
            </footer>
        </main>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-danger">
            <h2></h2>
            <div id="modalWarning" class="warning-box"></div>
            
            <div id="nameInputContainer" class="input-group-modal" style="display:none;">
                <label>Escribe <span id="targetNameDisplay" style="color:#d63384; user-select: all;"></span> para confirmar:</label>
                <input type="text" id="confirmInput" onkeyup="validateDeleteInput()" placeholder="Escribe el nombre aquí...">
            </div>

            <div id="reasonInputContainer" class="input-group-modal" style="display:none;">
                <label>Motivo de la acción:</label>
                <textarea id="reasonInput" rows="3" onkeyup="validateDeleteInput()" placeholder="Escribe el motivo..."></textarea>
            </div>

            <div class="modal-actions">
                <button onclick="closeDeleteModal()" class="btn-cancel-modal">Cancelar</button>
                <button id="btnConfirmDelete" onclick="executeDelete()" class="btn-delete-confirm">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        var globalVista = '<?php echo $vista; ?>';
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) { window.location.reload(); }
        });
    </script>
    <script src="js/advertencia.js"></script>
</body>
</html>