<?php
session_start();
require_once __DIR__ . '/../src/config/db.php'; 
$pdo = getConnection();

// --- GUARDIÁN DE LA PÁGINA ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: login.php");
    exit;
}

$vista = $_GET['vista'] ?? 'estudiantes'; 
$id_establecimiento = $_GET['id_establecimiento'] ?? null;
$id_curso = $_GET['id_curso'] ?? null;

// ======================================================================================
// LÓGICA DE ELIMINACIÓN (BORRADO LÓGICO EN CASCADA)
// ======================================================================================
if (isset($_GET['action']) && $_GET['action'] == 'eliminar') {
    $tipo = $_GET['tipo'] ?? '';
    $id = $_GET['id'] ?? null;

    if ($id && $tipo) {
        try {
            $pdo->beginTransaction();

            if ($tipo == 'establecimiento') {
                $stmt = $pdo->prepare("UPDATE Establecimiento SET Estado = 0, FechaEliminacion = NOW() WHERE Id = ?");
                $stmt->execute([$id]);
                $stmt = $pdo->prepare("UPDATE Curso SET Estado = 0, FechaEliminacion = NOW() WHERE Id_Establecimiento = ?");
                $stmt->execute([$id]);
                $sqlStu = "UPDATE Estudiante e INNER JOIN Curso c ON e.Id_Curso = c.Id SET e.Estado = 0, e.FechaEliminacion = NOW() WHERE c.Id_Establecimiento = ?";
                $stmt = $pdo->prepare($sqlStu);
                $stmt->execute([$id]);
                $_SESSION['success_message'] = "Establecimiento y todos sus datos asociados han sido eliminados.";

            } elseif ($tipo == 'curso') {
                $stmt = $pdo->prepare("UPDATE Curso SET Estado = 0, FechaEliminacion = NOW() WHERE Id = ?");
                $stmt->execute([$id]);
                $stmt = $pdo->prepare("UPDATE Estudiante SET Estado = 0, FechaEliminacion = NOW() WHERE Id_Curso = ?");
                $stmt->execute([$id]);
                $_SESSION['success_message'] = "Curso y sus estudiantes eliminados correctamente.";

            } elseif ($tipo == 'estudiante') {
                $stmt = $pdo->prepare("UPDATE Estudiante SET Estado = 0, FechaEliminacion = NOW() WHERE Id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_message'] = "Estudiante eliminado correctamente.";
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error crítico al eliminar: " . $e->getMessage();
        }
        $redirect_url = 'dashboard_admin_bd.php?vista=' . $vista;
        if ($id_establecimiento) $redirect_url .= '&id_establecimiento=' . $id_establecimiento;
        if ($id_curso) $redirect_url .= '&id_curso=' . $id_curso;
        header("Location: " . $redirect_url);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin BD - NutriMonitor</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/advertencia.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>
    
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                <div class="nav-category">Estudiantes</div>
                <a href="dashboard_admin_bd.php?vista=estudiantes" class="nav-item <?php echo ($vista == 'estudiantes') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-school"></i> Establecimientos
                </a>
                <div class="nav-category">Usuarios</div>
                <a href="dashboard_admin_bd.php?vista=usuarios" class="nav-item <?php echo ($vista == 'usuarios') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> Usuarios
                </a>
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
                    if (isset($_SESSION['success_message'])) {
                        echo '<div class="mensaje success">' . $_SESSION['success_message'] . '</div>';
                        unset($_SESSION['success_message']);
                    }
                    if (isset($_SESSION['error'])) {
                        echo '<div class="mensaje error">' . $_SESSION['error'] . '</div>';
                        unset($_SESSION['error']);
                    }
                    
                    if ($vista === 'usuarios') {
                        // VISTA USUARIOS
                        echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-users"></i> Gestión de Usuarios</h1><a href="AdminBD/crud_usuario/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Usuario</a></div>'; 
                        $stmt = $pdo->query("SELECT u.Id, u.Rut, u.Nombre, u.Apellido, u.Email, u.Estado, r.Nombre AS NombreRol FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id ORDER BY u.Apellido, u.Nombre");
                        echo "<div class='table-responsive'><table><thead><tr><th>RUT</th><th>Nombre Completo</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>";
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $estado = $row['Estado'] ? '<span class="status-active">Activo</span>' : '<span class="status-inactive">Inactivo</span>';
                            echo "<tr><td>".htmlspecialchars($row['Rut'])."</td><td>".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."</td><td>".htmlspecialchars($row['Email'])."</td><td>".htmlspecialchars($row['NombreRol'])."</td><td>$estado</td><td class='actions'><a href='AdminBD/crud_usuario/edit.php?id=".$row['Id']."' class='btn-action btn-edit'><i class='fa-solid fa-pencil'></i></a></td></tr>";
                        }
                        echo "</tbody></table></div>";

                    } else {
                        // VISTA ESTABLECIMIENTOS / CURSOS / ESTUDIANTES
                        echo '<nav class="breadcrumbs"><a href="dashboard_admin_bd.php?vista=estudiantes" class="'.(!$id_establecimiento ? 'active' : '').'">Establecimientos</a>';
                        if ($id_establecimiento) {
                            $stmt_est = $pdo->prepare("SELECT Nombre FROM Establecimiento WHERE Id = ?");
                            $stmt_est->execute([$id_establecimiento]);
                            echo '<span>></span> <a href="dashboard_admin_bd.php?vista=estudiantes&id_establecimiento='.$id_establecimiento.'" class="'.($id_establecimiento && !$id_curso ? 'active' : '').'">' . htmlspecialchars($stmt_est->fetchColumn()) . '</a>';
                        }
                        if ($id_curso) {
                            $stmt_cur = $pdo->prepare("SELECT Nombre FROM Curso WHERE Id = ?");
                            $stmt_cur->execute([$id_curso]);
                            echo '<span>></span> <span class="active">' . htmlspecialchars($stmt_cur->fetchColumn()) . '</span>';
                        }
                        echo '</nav>';

                        if ($id_establecimiento && $id_curso) {
                            // VISTA 3: Estudiantes (FILTRO ESTADO = 1)
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-children"></i> Estudiantes del Curso</h1><a href="AdminBD/crud_estudiante/create.php?id_curso='.$id_curso.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Estudiante</a></div>';
                            $stmt = $pdo->prepare("SELECT Id, Rut, Nombre, Apellido, FechaNacimiento, Estado FROM Estudiante WHERE Id_Curso = ? AND Estado = 1 ORDER BY Apellido, Nombre");
                            $stmt->execute([$id_curso]);
                            echo "<div class='table-responsive'><table><thead><tr><th>RUT</th><th>Nombre Completo</th><th>Fecha Nacimiento</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $estado = $row['Estado'] ? '<span class="status-active">Activo</span>' : '<span class="status-inactive">Inactivo</span>';
                                echo "<tr><td>".htmlspecialchars($row['Rut'])."</td><td>".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."</td><td>".htmlspecialchars($row['FechaNacimiento'])."</td><td>$estado</td><td class='actions'><a href='AdminBD/crud_estudiante/edit.php?id=".$row['Id']."' class='btn-action btn-edit'><i class='fa-solid fa-pencil'></i></a><a href='javascript:void(0);' onclick=\"confirmSimpleDelete('estudiante', ".$row['Id'].", '".htmlspecialchars($row['Nombre'].' '.$row['Apellido'])."', '$id_establecimiento', '$id_curso')\" class='btn-action btn-delete'><i class='fa-solid fa-trash-can'></i></a></td></tr>";
                            }
                            echo "</tbody></table></div>";

                        } else if ($id_establecimiento && !$id_curso) {
                            // VISTA 2: Cursos (FILTRO ESTADO = 1)
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-chalkboard-user"></i> Cursos del Establecimiento</h1><a href="AdminBD/crud_curso/create.php?id_establecimiento='.$id_establecimiento.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Curso</a></div>';
                            $stmt = $pdo->prepare("SELECT c.Id, c.Nombre, u.Nombre AS NombreProfesor, u.Apellido AS ApellidoProfesor FROM Curso c JOIN Usuario u ON c.Id_Profesor = u.Id WHERE c.Id_Establecimiento = ? AND c.Estado = 1 ORDER BY c.Nombre");
                            $stmt->execute([$id_establecimiento]);
                            echo "<div class='table-responsive'><table><thead><tr><th>Nombre del Curso</th><th>Profesor Asignado</th><th>Acciones</th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr><td>".htmlspecialchars($row['Nombre'])."</td><td>".htmlspecialchars($row['NombreProfesor'].' '.$row['ApellidoProfesor'])."</td><td class='actions'><a href='dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_establecimiento&id_curso=".$row['Id']."' class='btn-action btn-view'><i class='fa-solid fa-eye'></i></a><a href='AdminBD/crud_curso/edit.php?id=".$row['Id']."' class='btn-action btn-edit'><i class='fa-solid fa-pencil'></i></a><a href='javascript:void(0);' onclick=\"openDeleteModal('curso', ".$row['Id'].", '".htmlspecialchars($row['Nombre'])."', '$id_establecimiento')\" class='btn-action btn-delete'><i class='fa-solid fa-trash-can'></i></a></td></tr>";
                            }
                            echo "</tbody></table></div>";

                        } else {
                            // VISTA 1: Establecimientos (FILTRO ESTADO = 1)
                            echo '<div class="content-header-with-btn"><h1><i class="fa-solid fa-school"></i> Establecimientos</h1><a href="AdminBD/crud_establecimiento/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Establecimiento</a></div>';
                            $stmt = $pdo->query("SELECT e.Id, e.Nombre, d.Direccion, c.Comuna FROM Establecimiento e LEFT JOIN Direccion d ON e.Id_Direccion = d.Id LEFT JOIN Comuna c ON d.Id_Comuna = c.Id WHERE e.Estado = 1 ORDER BY e.Nombre");
                            echo "<div class='table-responsive'><table><thead><tr><th>Establecimiento</th><th>Dirección</th><th>Comuna</th><th>Acciones</th></tr></thead><tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr><td>".htmlspecialchars($row['Nombre'])."</td><td>".htmlspecialchars($row['Direccion'] ?? 'N/A')."</td><td>".htmlspecialchars($row['Comuna'] ?? 'N/A')."</td><td class='actions'><a href='dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=".$row['Id']."' class='btn-action btn-view'><i class='fa-solid fa-eye'></i></a><a href='AdminBD/crud_establecimiento/edit.php?id=".$row['Id']."' class='btn-action btn-edit'><i class='fa-solid fa-pencil'></i></a><a href='javascript:void(0);' onclick=\"openDeleteModal('establecimiento', ".$row['Id'].", '".htmlspecialchars($row['Nombre'])."')\" class='btn-action btn-delete'><i class='fa-solid fa-trash-can'></i></a></td></tr>";
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
            <p>Esta acción es <strong>IRREVERSIBLE</strong> y borrará permanentemente los datos seleccionados.</p>
            <div id="modalWarning" class="warning-box"></div>
            <div class="confirmation-input-container">
                <label>Por favor, escribe <span id="targetNameDisplay" style="color:#d63384; user-select: all;"></span> para confirmar:</label>
                <input type="text" id="confirmInput" onkeyup="validateDeleteInput()" placeholder="Escribe el nombre aquí...">
            </div>
            <div class="modal-actions">
                <button onclick="closeDeleteModal()" class="btn-cancel-modal">Cancelar</button>
                <button id="btnConfirmDelete" onclick="executeDelete()" class="btn-delete-confirm">Eliminar definitivamente</button>
            </div>
        </div>
    </div>

    <script src="js/advertencia.js"></script>

</body>
</html>