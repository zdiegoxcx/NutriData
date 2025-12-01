<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_curso = $_GET['id'] ?? null;
if (!$id_curso) {
    header("Location: ../../dashboard_admin_bd.php");
    exit;
}

// 1. Obtener datos del curso actual
$stmt = $pdo->prepare("SELECT * FROM Curso WHERE Id = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) die("Curso no encontrado.");

$id_establecimiento = $curso['Id_Establecimiento']; // Para volver atrás
$id_profesor_actual = $curso['Id_Profesor'];
$nombre_actual = $curso['Nombre'];

// 2. Descomponer el nombre (Ej: "1° Básico A" -> "1° Básico" y "A")
// Asumimos que la letra es siempre el último carácter o palabra tras un espacio.
$partes_nombre = explode(' ', $nombre_actual);
$letra_selected = array_pop($partes_nombre); // Saca el último elemento (La letra)
$grado_selected = implode(' ', $partes_nombre); // Une el resto (El grado)

// 3. Obtener lista de profesores para el buscador JS
$sql_profes = "SELECT u.Id, u.Nombre, u.Apellido, u.Rut 
               FROM Usuario u
               JOIN Rol r ON u.Id_Rol = r.Id
               WHERE r.Nombre = 'profesor' AND u.Estado = 1
               ORDER BY u.Apellido, u.Nombre";
$profesores = $pdo->query($sql_profes)->fetchAll(PDO::FETCH_ASSOC);

// Buscar datos del profesor actual para mostrarlo seleccionado
$profesor_actual_data = null;
foreach ($profesores as $p) {
    if ($p['Id'] == $id_profesor_actual) {
        $profesor_actual_data = $p;
        break;
    }
}

$errores = [];

// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grado = $_POST['grado'];
    $letra = $_POST['letra'];
    $profesor_id = $_POST['profesor_id'];

    if (empty($grado)) $errores[] = "Debe seleccionar un grado.";
    if (empty($letra)) $errores[] = "Debe seleccionar una letra.";
    if (empty($profesor_id)) $errores[] = "Debe asignar un profesor encargado.";

    // Construir nuevo nombre
    $nombre_nuevo = "$grado $letra";

    // Validar duplicados (Excluyendo el ID actual)
    if (empty($errores)) {
        $stmt_check = $pdo->prepare("SELECT Id FROM Curso WHERE Id_Establecimiento = ? AND Nombre = ? AND Estado = 1 AND Id != ?");
        $stmt_check->execute([$id_establecimiento, $nombre_nuevo, $id_curso]);
        if ($stmt_check->rowCount() > 0) {
            $errores[] = "El curso '$nombre_nuevo' ya existe en este establecimiento.";
        }
    }

    if (empty($errores)) {
        try {
            $update = $pdo->prepare("UPDATE Curso SET Nombre = ?, Id_Profesor = ? WHERE Id = ?");
            $update->execute([$nombre_nuevo, $profesor_id, $id_curso]);
            
            $_SESSION['success_message'] = "Curso actualizado correctamente a <strong>$nombre_nuevo</strong>.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=" . $id_establecimiento);
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error DB: " . $e->getMessage();
        }
    } else {
        // Si hay error, mantenemos la selección del POST para no perder lo que el usuario cambió
        $grado_selected = $grado;
        $letra_selected = $letra;
        // Para el profesor, necesitaríamos buscar de nuevo sus datos si cambió el ID, 
        // pero por simplicidad, si falla, el JS usará el ID que quedó en el input hidden.
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Curso</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos del buscador (Mismos de create.php) */
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
            display: flex; align-items: center; justify-content: space-between;
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
                    <h1><i class="fa-solid fa-pencil"></i> Editar Curso</h1>

                    <?php if ($errores): ?>
                        <div class="mensaje error"><ul><?php foreach ($errores as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="crud-form">
                        
                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group" style="flex: 2;">
                                <label for="grado">Nivel / Grado:</label>
                                <select id="grado" name="grado" required>
                                    <option value="">Seleccione Grado...</option>
                                    <optgroup label="Pre-Básica">
                                        <option value="Pre-Kinder" <?php echo ($grado_selected == 'Pre-Kinder') ? 'selected' : ''; ?>>Pre-Kinder</option>
                                        <option value="Kinder" <?php echo ($grado_selected == 'Kinder') ? 'selected' : ''; ?>>Kinder</option>
                                    </optgroup>
                                    <optgroup label="Básica">
                                        <?php for($i=1; $i<=8; $i++): 
                                            $val = "{$i}° Básico";
                                            $sel = ($grado_selected == $val) ? 'selected' : '';
                                            echo "<option value='$val' $sel>$val</option>";
                                        endfor; ?>
                                    </optgroup>
                                    <optgroup label="Media">
                                        <?php for($i=1; $i<=4; $i++): 
                                            $val = "{$i}° Medio";
                                            $sel = ($grado_selected == $val) ? 'selected' : '';
                                            echo "<option value='$val' $sel>$val</option>";
                                        endfor; ?>
                                    </optgroup>
                                </select>
                            </div>
                            
                            <div class="form-group" style="flex: 1;">
                                <label for="letra">Letra:</label>
                                <select id="letra" name="letra" required>
                                    <option value="">Letra...</option>
                                    <?php foreach(range('A', 'F') as $l): 
                                        $sel = ($letra_selected == $l) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $l; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Profesor Encargado:</label>
                            <input type="hidden" id="profesor_id" name="profesor_id" value="<?php echo $id_profesor_actual; ?>" required>
                            
                            <div class="search-box">
                                <input type="text" id="search_input" class="search-input" 
                                       placeholder="Buscar profesor por nombre o RUT..." 
                                       autocomplete="off"
                                       style="<?php echo $profesor_actual_data ? 'display:none;' : 'display:block;'; ?>">
                                <div id="profesor_list" class="profesor-list"></div>
                            </div>

                            <div id="selected_display" class="selected-profesor" 
                                 style="<?php echo $profesor_actual_data ? 'display:flex;' : 'display:none;'; ?>">
                                <span id="selected_name">
                                    <?php if ($profesor_actual_data): ?>
                                        <strong><?php echo htmlspecialchars($profesor_actual_data['Nombre'] . ' ' . $profesor_actual_data['Apellido']); ?></strong> 
                                        (<?php echo htmlspecialchars($profesor_actual_data['Rut']); ?>)
                                    <?php else: ?>
                                        Profesor seleccionado
                                    <?php endif; ?>
                                </span>
                                <button type="button" onclick="clearSelection()" style="background:none; border:none; cursor:pointer; color:#dc3545;" title="Cambiar profesor">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 30px;">
                            <button type="submit" class="btn-create" style="background:#ffc107; color:black; width: 100%; padding: 12px;">
                                <i class="fa-solid fa-save"></i> Actualizar Curso
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script>
        const profesores = <?php echo json_encode($profesores); ?>;
        
        const searchInput = document.getElementById('search_input');
        const listContainer = document.getElementById('profesor_list');
        const hiddenInput = document.getElementById('profesor_id');
        const selectedDisplay = document.getElementById('selected_display');
        const selectedName = document.getElementById('selected_name');

        searchInput.addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            listContainer.innerHTML = '';

            if (term.length < 1) {
                listContainer.style.display = 'none';
                return;
            }

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
            hiddenInput.value = profesor.Id;
            selectedName.innerHTML = `<strong>${profesor.Nombre} ${profesor.Apellido}</strong> (${profesor.Rut})`;
            selectedDisplay.style.display = 'flex';
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

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !listContainer.contains(e.target)) {
                listContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>