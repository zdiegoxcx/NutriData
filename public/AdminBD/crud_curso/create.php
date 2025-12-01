<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_establecimiento = $_GET['id_establecimiento'] ?? null;
if (!$id_establecimiento) {
    header("Location: ../../dashboard_admin_bd.php?vista=estudiantes");
    exit;
}

$errores = [];
$grado = $letra = $profesor_id = '';

// --- OBTENER LISTA DE PROFESORES (Para JS) ---
// Traemos todos los profesores activos para el buscador
$sql_profes = "SELECT u.Id, u.Nombre, u.Apellido, u.Rut 
               FROM Usuario u
               JOIN Rol r ON u.Id_Rol = r.Id
               WHERE r.Nombre = 'profesor' AND u.Estado = 1
               ORDER BY u.Apellido, u.Nombre";
$profesores = $pdo->query($sql_profes)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grado = $_POST['grado'];
    $letra = $_POST['letra'];
    $profesor_id = $_POST['profesor_id'];

    // Validaciones
    if (empty($grado)) $errores[] = "Debe seleccionar un grado.";
    if (empty($letra)) $errores[] = "Debe seleccionar una letra.";
    if (empty($profesor_id)) $errores[] = "Debe buscar y seleccionar un profesor encargado.";

    // Construir nombre del curso
    $nombre_curso = "$grado $letra";

    // Validar que no exista el mismo curso en este colegio
    if (empty($errores)) {
        $stmt_check = $pdo->prepare("SELECT Id FROM Curso WHERE Id_Establecimiento = ? AND Nombre = ? AND Estado = 1");
        $stmt_check->execute([$id_establecimiento, $nombre_curso]);
        if ($stmt_check->rowCount() > 0) {
            $errores[] = "El curso '$nombre_curso' ya existe en este establecimiento.";
        }
    }

    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor, Estado) VALUES (?, ?, ?, 1)");
            $stmt->execute([$id_establecimiento, $nombre_curso, $profesor_id]);

            $_SESSION['success_message'] = "Curso <strong>$nombre_curso</strong> creado exitosamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=" . $id_establecimiento);
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error al crear curso: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Curso</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos para el buscador de profesores */
        .search-box { position: relative; }
        .search-input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
        .profesor-list {
            max-height: 200px; overflow-y: auto; border: 1px solid #ddd; 
            border-top: none; display: none; position: absolute; 
            background: white; width: 100%; z-index: 10;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .profesor-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .profesor-item:hover { background-color: #f0f8ff; color: #0d6efd; }
        .selected-profesor { 
            margin-top: 10px; padding: 10px; background: #e7f1ff; 
            border: 1px solid #b6d4fe; border-radius: 6px; color: #084298; 
            display: none; align-items: center; justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>DAEM NutriMonitor</h2></div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $id_establecimiento; ?>" class="nav-item active">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></div>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-chalkboard-user"></i> Crear Nuevo Curso</h1>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <ul><?php foreach ($errores as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form action="create.php?id_establecimiento=<?php echo $id_establecimiento; ?>" method="POST" class="crud-form">
                        
                        <!-- SELECCIÓN DE GRADO Y LETRA -->
                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group" style="flex: 2;">
                                <label for="grado">Nivel / Grado:</label>
                                <select id="grado" name="grado" required>
                                    <option value="">Seleccione Grado...</option>
                                    <optgroup label="Pre-Básica">
                                        <option value="Pre-Kinder">Pre-Kinder</option>
                                        <option value="Kinder">Kinder</option>
                                    </optgroup>
                                    <optgroup label="Básica">
                                        <?php for($i=1; $i<=8; $i++) echo "<option value='{$i}° Básico'>{$i}° Básico</option>"; ?>
                                    </optgroup>
                                    <optgroup label="Media">
                                        <?php for($i=1; $i<=4; $i++) echo "<option value='{$i}° Medio'>{$i}° Medio</option>"; ?>
                                    </optgroup>
                                </select>
                            </div>
                            
                            <div class="form-group" style="flex: 1;">
                                <label for="letra">Letra:</label>
                                <select id="letra" name="letra" required>
                                    <option value="">Letra...</option>
                                    <?php foreach(range('A', 'F') as $l): ?>
                                        <option value="<?php echo $l; ?>"><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- BUSCADOR DE PROFESORES -->
                        <div class="form-group">
                            <label>Profesor Encargado:</label>
                            <input type="hidden" id="profesor_id" name="profesor_id" required>
                            
                            <div class="search-box">
                                <input type="text" id="search_input" class="search-input" placeholder="Escriba nombre o RUT del profesor..." autocomplete="off">
                                <div id="profesor_list" class="profesor-list">
                                    <!-- Aquí se llenará con JS -->
                                </div>
                            </div>

                            <!-- Visualización de selección -->
                            <div id="selected_display" class="selected-profesor">
                                <span id="selected_name">Profesor seleccionado</span>
                                <button type="button" onclick="clearSelection()" style="background:none; border:none; cursor:pointer; color:#dc3545;">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            
                            <?php if(empty($profesores)): ?>
                                <small style="color: #dc3545; margin-top:5px; display:block;">* No hay profesores activos registrados en el sistema.</small>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions" style="margin-top: 30px;">
                            <button type="submit" class="btn-create" style="cursor:pointer; width: 100%; padding: 12px;">
                                <i class="fa-solid fa-save"></i> Guardar Curso
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <!-- LÓGICA DEL BUSCADOR -->
    <script>
        // Datos desde PHP a JS
        const profesores = <?php echo json_encode($profesores); ?>;
        
        const searchInput = document.getElementById('search_input');
        const listContainer = document.getElementById('profesor_list');
        const hiddenInput = document.getElementById('profesor_id');
        const selectedDisplay = document.getElementById('selected_display');
        const selectedName = document.getElementById('selected_name');

        // Evento al escribir
        searchInput.addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            listContainer.innerHTML = '';

            if (term.length < 1) {
                listContainer.style.display = 'none';
                return;
            }

            // Filtrar array
            const filtered = profesores.filter(p => 
                p.Nombre.toLowerCase().includes(term) || 
                p.Apellido.toLowerCase().includes(term) || 
                p.Rut.includes(term)
            );

            if (filtered.length > 0) {
                listContainer.style.display = 'block';
                filtered.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'profesor-item';
                    div.innerHTML = `<strong>${p.Nombre} ${p.Apellido}</strong> <br> <small style='color:#666'>RUT: ${p.Rut}</small>`;
                    div.onclick = () => selectProfesor(p);
                    listContainer.appendChild(div);
                });
            } else {
                listContainer.style.display = 'none';
            }
        });

        function selectProfesor(profesor) {
            // Guardar ID
            hiddenInput.value = profesor.Id;
            
            // Mostrar selección
            selectedName.innerHTML = `<strong>${profesor.Nombre} ${profesor.Apellido}</strong> (${profesor.Rut})`;
            selectedDisplay.style.display = 'flex';
            
            // Ocultar buscador
            searchInput.style.display = 'none';
            listContainer.style.display = 'none';
        }

        function clearSelection() {
            hiddenInput.value = '';
            selectedDisplay.style.display = 'none';
            searchInput.style.display = 'block';
            searchInput.value = '';
            searchInput.focus();
        }

        // Cerrar lista al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !listContainer.contains(e.target)) {
                listContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>