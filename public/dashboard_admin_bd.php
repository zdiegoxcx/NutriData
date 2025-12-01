<?php
session_start();
require_once __DIR__ . '/../src/config/db.php'; 
$pdo = getConnection();

// --- GUARDIÁN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: login.php");
    exit;
}

// Capturamos la vista actual para mantenerla en redirecciones
$vista = $_GET['vista'] ?? 'estudiantes'; 
$id_establecimiento = $_GET['id_establecimiento'] ?? null;
$id_curso = $_GET['id_curso'] ?? null;

// ======================================================================================
// LÓGICA DE ACCIONES (ELIMINAR Y REACTIVAR)
// ======================================================================================
if (isset($_GET['action'])) {
    $id = $_GET['id'] ?? null;
    $accion = $_GET['action'];

    if ($id) {
        try {
            $pdo->beginTransaction();

            // --- CASO 1: ELIMINAR / DESACTIVAR ---
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
                    $_SESSION['success_message'] = "Usuario desactivado correctamente.";
                }
            }
            
            // --- CASO 2: REACTIVAR USUARIO ---
            elseif ($accion == 'reactivar') {
                // Reactivamos: Estado 1, Fecha y Motivo NULL
                $pdo->prepare("UPDATE Usuario SET Estado = 1, FechaEliminacion = NULL, MotivoEliminacion = NULL WHERE Id = ?")->execute([$id]);
                $_SESSION['success_message'] = "Usuario reactivado exitosamente.";
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        // Redirección inteligente manteniendo la vista
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
                    
                    /* ========================== VISTA USUARIOS ========================== */
                    if ($vista === 'usuarios') {
                        echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-users"></i> Gestión de Usuarios</h1><a href="AdminBD/crud_usuario/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Usuario</a></div>'; 
                        $stmt = $pdo->query("SELECT u.Id, u.Rut, u.Nombre, u.Apellido, u.Email, u.Estado, r.Nombre AS NombreRol FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id ORDER BY u.Apellido, u.Nombre");
                        
                        echo "<div class='table-responsive'><table><thead><tr>
                                <th>RUT</th><th>Nombre Completo</th><th>Email</th><th>Rol</th><th>Estado</th><th></th>
                              </tr></thead><tbody>";
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $es_activo = ($row['Estado'] == 1);
                            $estado_html = $es_activo ? '<span class="status-active">Activo</span>' : '<span class="status-inactive">Inactivo</span>';
                            
                            echo "<tr class='clickable-row'>
                                    <td>".htmlspecialchars($row['Rut'])."</td>
                                    <td>".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."</td>
                                    <td>".htmlspecialchars($row['Email'])."</td>
                                    <td>".htmlspecialchars($row['NombreRol'])."</td>
                                    <td>$estado_html</td>
                                    <td class='menu-column'>
                                        <div class='dropdown'>
                                            <button class='btn-dots' onclick=\"toggleMenu(event, 'u".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button>
                                            <div id='menu-u".$row['Id']."' class='dropdown-menu'>";
                                            
                                            // LÓGICA DEL MENÚ: Activo vs Inactivo
                                            if ($es_activo) {
                                                echo "<a href='AdminBD/crud_usuario/edit.php?id=".$row['Id']."'><i class='fa-solid fa-pencil'></i> Editar</a>";
                                                echo "<a href='javascript:void(0);' class='danger-action' onclick=\"openDeleteModal('usuario', ".$row['Id'].", '".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."')\"><i class='fa-solid fa-ban'></i> Desactivar</a>";
                                            } else {
                                                echo "<a href='AdminBD/crud_usuario/edit.php?id=".$row['Id']."'><i class='fa-solid fa-eye'></i> Ver Detalle</a>";
                                                echo "<a href='javascript:void(0);' class='success-action' onclick=\"confirmReactivate(".$row['Id'].", '".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."')\"><i class='fa-solid fa-rotate-left'></i> Reactivar</a>";
                                            }

                            echo "          </div>
                                        </div>
                                    </td>
                                  </tr>";
                        }
                        echo "</tbody></table></div>";

                    } else {
                        /* ========================== VISTAS DE ESTRUCTURA ========================== */
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

                        if ($id_establecimiento && $id_curso) {
                            // --- VISTA 3: ESTUDIANTES ---
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-children"></i> Estudiantes</h1><a href="AdminBD/crud_estudiante/create.php?id_curso='.$id_curso.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear</a></div>';
                            $stmt = $pdo->prepare("SELECT Id, Rut, Nombre, Apellido, FechaNacimiento FROM Estudiante WHERE Id_Curso = ? AND Estado = 1 ORDER BY Apellido, Nombre");
                            $stmt->execute([$id_curso]);
                            echo "<div class='table-responsive'><table><thead><tr><th>RUT</th><th>Nombre</th><th>Fecha Nac.</th><th></th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr class='clickable-row'>
                                        <td>".htmlspecialchars($row['Rut'])."</td>
                                        <td>".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."</td>
                                        <td>".htmlspecialchars($row['FechaNacimiento'])."</td>
                                        <td class='menu-column'>
                                            <div class='dropdown'>
                                                <button class='btn-dots' onclick=\"toggleMenu(event, 's".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button>
                                                <div id='menu-s".$row['Id']."' class='dropdown-menu'>
                                                    <a href='AdminBD/crud_estudiante/edit.php?id=".$row['Id']."'><i class='fa-solid fa-pencil'></i> Editar</a>
                                                    <a href='javascript:void(0);' class='danger-action' onclick=\"openDeleteModal('estudiante', ".$row['Id'].", '".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."', '$id_establecimiento', '$id_curso')\"><i class='fa-solid fa-trash-can'></i> Eliminar</a>
                                                </div>
                                            </div>
                                        </td>
                                      </tr>";
                            }
                            echo "</tbody></table></div>";

                        } else if ($id_establecimiento && !$id_curso) {
                            // --- VISTA 2: CURSOS ---
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-chalkboard-user"></i> Cursos</h1><a href="AdminBD/crud_curso/create.php?id_establecimiento='.$id_establecimiento.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Curso</a></div>';
                            $stmt = $pdo->prepare("SELECT c.Id, c.Nombre, u.Nombre AS NProf, u.Apellido AS AProf FROM Curso c JOIN Usuario u ON c.Id_Profesor = u.Id WHERE c.Id_Establecimiento = ? AND c.Estado = 1 ORDER BY c.Nombre");
                            $stmt->execute([$id_establecimiento]);
                            echo "<div class='table-responsive'><table><thead><tr><th>Curso</th><th>Profesor</th><th></th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $url = "dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_establecimiento&id_curso=".$row['Id'];
                                echo "<tr class='clickable-row' onclick=\"window.location='$url'\">
                                        <td>".htmlspecialchars($row['Nombre'])."</td>
                                        <td>".htmlspecialchars($row['NProf'].' '.$row['AProf'])."</td>
                                        <td class='menu-column'>
                                            <div class='dropdown'>
                                                <button class='btn-dots' onclick=\"toggleMenu(event, 'c".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button>
                                                <div id='menu-c".$row['Id']."' class='dropdown-menu'>
                                                    <a href='AdminBD/crud_curso/edit.php?id=".$row['Id']."'><i class='fa-solid fa-pencil'></i> Editar</a>
                                                    <a href='javascript:void(0);' class='danger-action' onclick=\"openDeleteModal('curso', ".$row['Id'].", '".htmlspecialchars($row['Nombre'])."', '$id_establecimiento')\"><i class='fa-solid fa-trash-can'></i> Eliminar</a>
                                                </div>
                                            </div>
                                        </td>
                                      </tr>";
                            }
                            echo "</tbody></table></div>";

                        } else {
                            // --- VISTA 1: ESTABLECIMIENTOS ---
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-school"></i> Establecimientos</h1><a href="AdminBD/crud_establecimiento/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Establecimiento</a></div>';
                            $stmt = $pdo->query("SELECT e.Id, e.Nombre, d.Direccion, c.Comuna FROM Establecimiento e LEFT JOIN Direccion d ON e.Id_Direccion = d.Id LEFT JOIN Comuna c ON d.Id_Comuna = c.Id WHERE e.Estado = 1 ORDER BY e.Nombre");
                            echo "<div class='table-responsive'><table><thead><tr><th>Establecimiento</th><th>Dirección</th><th>Comuna</th><th></th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $url = "dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=".$row['Id'];
                                echo "<tr class='clickable-row' onclick=\"window.location='$url'\">
                                        <td>".htmlspecialchars($row['Nombre'])."</td>
                                        <td>".htmlspecialchars($row['Direccion'] ?? '')."</td>
                                        <td>".htmlspecialchars($row['Comuna'] ?? '')."</td>
                                        <td class='menu-column'>
                                            <div class='dropdown'>
                                                <button class='btn-dots' onclick=\"toggleMenu(event, 'e".$row['Id']."')\"><i class='fa-solid fa-ellipsis-vertical'></i></button>
                                                <div id='menu-e".$row['Id']."' class='dropdown-menu'>
                                                    <a href='AdminBD/crud_establecimiento/edit.php?id=".$row['Id']."'><i class='fa-solid fa-pencil'></i> Editar</a>
                                                    <a href='javascript:void(0);' class='danger-action' onclick=\"openDeleteModal('establecimiento', ".$row['Id'].", '".htmlspecialchars($row['Nombre'])."')\"><i class='fa-solid fa-trash-can'></i> Eliminar</a>
                                                </div>
                                            </div>
                                        </td>
                                      </tr>";
                            }
                            echo "</tbody></table></div>";
                        }
                    }
                    ?>
                </div>
            </section>
        </main>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-danger">
            <h2><i class="fa-solid fa-triangle-exclamation"></i> ¿Estás absolutamente seguro?</h2>
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
        // Inyectamos la variable de sesión actual al JS
        var globalVista = '<?php echo $vista; ?>';
    </script>
    <script src="js/advertencia.js"></script>
</body>
</html>