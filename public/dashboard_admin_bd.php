
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

// --- Lógica para eliminar ---
if (isset($_GET['action']) && $_GET['action'] == 'eliminar') {
    $tipo = $_GET['tipo'] ?? '';
    $id = $_GET['id'] ?? null;

    if ($id && $tipo) {
        $tabla = '';
        switch ($tipo) {
            case 'establecimiento': $tabla = 'Establecimiento'; break;
            case 'curso': $tabla = 'Curso'; break;
            case 'estudiante': $tabla = 'Estudiante'; break;
            case 'usuario': $tabla = 'Usuario'; break;
        }

        if ($tabla) {
            try {
                // Antes de eliminar, verificar dependencias si es necesario
                // Para simplificar, aquí se asume que las FKs están configuradas ON DELETE CASCADE o se manejan.
                // Si no, PDO lanzará una excepción y la capturaremos.
                $stmt_del = $pdo->prepare("DELETE FROM $tabla WHERE Id = ?");
                $stmt_del->execute([$id]);
                // Redirigir para limpiar los parámetros GET de la eliminación
                $redirect_url = 'dashboard_admin_bd.php?vista=' . $vista;
                if ($id_establecimiento) $redirect_url .= '&id_establecimiento=' . $id_establecimiento;
                if ($id_curso) $redirect_url .= '&id_curso=' . $id_curso;
                header("Location: " . $redirect_url);
                exit;
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error al eliminar: " . $e->getMessage();
                // Redirigir a la misma página para mostrar el error
                $redirect_url = 'dashboard_admin_bd.php?vista=' . $vista;
                if ($id_establecimiento) $redirect_url .= '&id_establecimiento=' . $id_establecimiento;
                if ($id_curso) $redirect_url .= '&id_curso=' . $id_curso;
                header("Location: " . $redirect_url);
                exit;
            }
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
    <div class="dashboard-wrapper">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DAEM NutriMonitor</h2>
            </div>
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
                <div class="header-user">
                    <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>
                </div>
                <a href="logout.php" class="btn-logout">Cerrar Sesion</a>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <?php
                    if (isset($_SESSION['error'])) {
                        echo '<div class="mensaje error">' . $_SESSION['error'] . '</div>';
                        unset($_SESSION['error']);
                    }
                    ?>
                    
                    <?php
                    // --- SWITCH DE VISTAS ---
                    
                    if ($vista === 'usuarios') {
                        // ---------------------------------
                        // --- VISTA: GESTIÓN DE USUARIOS ---
                        // ---------------------------------
                        echo '<div class="content-header-with-btn">';
                        echo "<h1><i class='fa-solid fa-users'></i> Gestión de Usuarios</h1>";
                        echo '<a href="crud_usuario/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Usuario</a>';
                        echo '</div>'; // Fin content-header-with-btn
                        
                        $stmt = $pdo->query("SELECT u.Id, u.Rut, u.Nombre, u.Apellido, u.Email, u.Estado, r.Nombre AS NombreRol 
                                              FROM Usuario u 
                                              JOIN Rol r ON u.Id_Rol = r.Id 
                                              ORDER BY u.Apellido, u.Nombre");
                        
                        echo "<div class='table-responsive'>";
                        echo "<table>";
                        echo "<thead><tr><th>RUT</th><th>Nombre Completo</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>";
                        echo "<tbody>";
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $estado = $row['Estado'] ? '<span class="status-active">Activo</span>' : '<span class="status-inactive">Inactivo</span>';
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['Rut']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Nombre'] . ' ' . $row['Apellido']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Email']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['NombreRol']) . "</td>";
                            echo "<td>" . $estado . "</td>";
                            echo '<td class="actions">';
                            echo '<a href="crud_usuario/edit.php?id='.$row['Id'].'" class="btn-action btn-edit" title="Editar"><i class="fa-solid fa-pencil"></i></a>';
                            echo '<a href="javascript:void(0);" onclick="confirmDelete(\'usuario\', '.$row['Id'].', \''.htmlspecialchars($row['Nombre'] . ' ' . $row['Apellido']).'\')" class="btn-action btn-delete" title="Eliminar"><i class="fa-solid fa-trash-can"></i></a>';
                            echo '</td>';
                            echo "</tr>";
                        }
                        echo "</tbody></table>";
                        echo "</div>";

                    } else {
                        // ---------------------------------------------------
                        // --- VISTA: FLUJO DE ESTUDIANTES (POR DEFECTO) ---
                        // ---------------------------------------------------
                        
                        // "Breadcrumbs" o Migas de pan para navegar
                        echo '<nav class="breadcrumbs">';
                        echo '<a href="dashboard_admin_bd.php?vista=estudiantes" class="'.(!$id_establecimiento ? 'active' : '').'">Establecimientos</a>';

                        if ($id_establecimiento) {
                            $stmt_est = $pdo->prepare("SELECT Nombre FROM Establecimiento WHERE Id = ?");
                            $stmt_est->execute([$id_establecimiento]);
                            $nombre_est = $stmt_est->fetchColumn();
                            echo '<span>></span> <a href="dashboard_admin_bd.php?vista=estudiantes&id_establecimiento='.$id_establecimiento.'" class="'.($id_establecimiento && !$id_curso ? 'active' : '').'">' . htmlspecialchars($nombre_est) . '</a>';
                        }
                        if ($id_curso) {
                            $stmt_cur = $pdo->prepare("SELECT Nombre FROM Curso WHERE Id = ?");
                            $stmt_cur->execute([$id_curso]);
                            $nombre_cur = $stmt_cur->fetchColumn();
                            echo '<span>></span> <span class="active">' . htmlspecialchars($nombre_cur) . '</span>';
                        }
                        echo '</nav>';


                        if ($id_establecimiento && $id_curso) {
                            // --- VISTA 3: Mostrar Estudiantes de un Curso ---
                            echo '<div class="content-header-with-btn">';
                            echo "<h1><i class='fa-solid fa-children'></i> Estudiantes del Curso</h1>";
                            echo '<a href="crud_estudiante/create.php?id_curso='.$id_curso.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Estudiante</a>';
                            echo '</div>'; // Fin content-header-with-btn
                            
                            $stmt = $pdo->prepare("SELECT Id, Rut, Nombre, Apellido, FechaNacimiento, Estado FROM Estudiante WHERE Id_Curso = ? ORDER BY Apellido, Nombre");
                            $stmt->execute([$id_curso]);

                            echo "<div class='table-responsive'>";
                            echo "<table>";
                            echo "<thead><tr><th>RUT</th><th>Nombre Completo</th><th>Fecha Nacimiento</th><th>Estado</th><th>Acciones</th></tr></thead>";
                            echo "<tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $estado = $row['Estado'] ? '<span class="status-active">Activo</span>' : '<span class="status-inactive">Inactivo</span>';
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['Rut']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['Nombre'] . ' ' . $row['Apellido']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['FechaNacimiento']) . "</td>";
                                echo "<td>" . $estado . "</td>";
                                echo '<td class="actions">';
                                echo '<a href="crud_estudiante/edit.php?id='.$row['Id'].'" class="btn-action btn-edit" title="Editar"><i class="fa-solid fa-pencil"></i></a>';
                                echo '<a href="javascript:void(0);" onclick="confirmDelete(\'estudiante\', '.$row['Id'].', \''.htmlspecialchars($row['Nombre'] . ' ' . $row['Apellido']).'\', \''.$id_establecimiento.'\', \''.$id_curso.'\')" class="btn-action btn-delete" title="Eliminar"><i class="fa-solid fa-trash-can"></i></a>';
                                echo '</td>';
                                echo "</tr>";
                            }
                            echo "</tbody></table>";
                            echo "</div>";

                        } else if ($id_establecimiento && !$id_curso) {
                            // --- VISTA 2: Mostrar Cursos de un Establecimiento ---
                            echo '<div class="content-header-with-btn">';
                            echo "<h1><i class='fa-solid fa-chalkboard-user'></i> Cursos del Establecimiento</h1>";
                            echo '<a href="crud_curso/create.php?id_establecimiento='.$id_establecimiento.'" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Curso</a>';
                            echo '</div>'; // Fin content-header-with-btn
                            
                            $stmt = $pdo->prepare("SELECT c.Id, c.Nombre, u.Nombre AS NombreProfesor, u.Apellido AS ApellidoProfesor 
                                                 FROM Curso c
                                                 JOIN Usuario u ON c.Id_Profesor = u.Id
                                                 WHERE c.Id_Establecimiento = ? ORDER BY c.Nombre");
                            $stmt->execute([$id_establecimiento]);
                            
                            echo "<div class='table-responsive'>";
                            echo "<table>";
                            echo "<thead><tr><th>Nombre del Curso</th><th>Profesor Asignado</th><th>Acciones</th></tr></thead>";
                            echo "<tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['Nombre']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['NombreProfesor'] . ' ' . $row['ApellidoProfesor']) . "</td>";
                                echo '<td class="actions">';
                                echo '<a href="dashboard_admin_bd.php?vista=estudiantes&id_establecimiento='.$id_establecimiento.'&id_curso='.$row['Id'].'" class="btn-action btn-view" title="Ver Estudiantes"><i class="fa-solid fa-eye"></i></a>';
                                echo '<a href="crud_curso/edit.php?id='.$row['Id'].'" class="btn-action btn-edit" title="Editar"><i class="fa-solid fa-pencil"></i></a>';
                                echo '<a href="javascript:void(0);" onclick="confirmDelete(\'curso\', '.$row['Id'].', \''.htmlspecialchars($row['Nombre']).'\', \''.$id_establecimiento.'\')" class="btn-action btn-delete" title="Eliminar"><i class="fa-solid fa-trash-can"></i></a>';
                                echo '</td>';
                                echo "</tr>";
                            }
                            echo "</tbody></table>";
                            echo "</div>";

                        } else {
                            // --- VISTA 1: Mostrar todos los Establecimientos (Default) ---
                            echo '<div class="content-header-with-btn">';
                            echo "<h1><i class='fa-solid fa-school'></i> Establecimientos</h1>";
                            echo '<a href="crud_establecimiento/create.php" class="btn-create"><i class="fa-solid fa-plus"></i> Crear Establecimiento</a>';
                            echo '</div>'; // Fin content-header-with-btn
                            
                            $stmt = $pdo->query("SELECT e.Id, e.Nombre, d.Direccion, c.Comuna 
                                                 FROM Establecimiento e 
                                                 LEFT JOIN Direccion d ON e.Id_Direccion = d.Id
                                                 LEFT JOIN Comuna c ON d.Id_Comuna = c.Id
                                                 ORDER BY e.Nombre");
                            
                            echo "<div class='table-responsive'>";
                            echo "<table>";
                            echo "<thead><tr><th>Establecimiento</th><th>Dirección</th><th>Comuna</th><th>Acciones</th></tr></thead>";
                            echo "<tbody>";
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['Nombre']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['Direccion'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($row['Comuna'] ?? 'N/A') . "</td>";
                                echo '<td class="actions">';
                                echo '<a href="dashboard_admin_bd.php?vista=estudiantes&id_establecimiento='.$row['Id'].'" class="btn-action btn-view" title="Ver Cursos"><i class="fa-solid fa-eye"></i></a>';
                                echo '<a href="crud_establecimiento/edit.php?id='.$row['Id'].'" class="btn-action btn-edit" title="Editar"><i class="fa-solid fa-pencil"></i></a>';
                                echo '<a href="javascript:void(0);" onclick="confirmDelete(\'establecimiento\', '.$row['Id'].', \''.htmlspecialchars($row['Nombre']).'\')" class="btn-action btn-delete" title="Eliminar"><i class="fa-solid fa-trash-can"></i></a>';
                                echo '</td>';
                                echo "</tr>";
                            }
                            echo "</tbody></table>";
                            echo "</div>";
                        }
                    }
                    ?>
                    
                </div>
            </section>
        </main>
    </div>

    <script>
        function confirmDelete(tipo, id, nombre, id_establecimiento = null, id_curso = null) {
            if (confirm(`¿Estás seguro de que quieres eliminar ${tipo} "${nombre}"? Esta acción no se puede deshacer.`)) {
                let url = `dashboard_admin_bd.php?action=eliminar&tipo=${tipo}&id=${id}`;
                if (id_establecimiento) url += `&id_establecimiento=${id_establecimiento}`;
                if (id_curso) url += `&id_curso=${id_curso}`;
                window.location.href = url;
            }
        }
    </script>

</body>
</html>