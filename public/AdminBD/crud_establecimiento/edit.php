<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN DE LA PÁGINA ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_establecimiento = $_GET['id'] ?? null;

if (!$id_establecimiento) {
    $_SESSION['error'] = "ID de establecimiento no especificado.";
    header("Location: ../../dashboard_admin_bd.php?vista=estudiantes");
    exit;
}

$errores = [];
$nombre = $direccion = $region_id = $comuna_id = '';
$id_direccion = null;

// --- 1. OBTENER DATOS ACTUALES DEL ESTABLECIMIENTO ---
try {
    // Hacemos JOIN para obtener dirección, comuna y región en una sola consulta
    $sql = "SELECT 
                e.Nombre, 
                e.Id_Direccion,
                d.Direccion, 
                d.Id_Comuna,
                c.Id_Region
            FROM Establecimiento e
            JOIN Direccion d ON e.Id_Direccion = d.Id
            JOIN Comuna c ON d.Id_Comuna = c.Id
            WHERE e.Id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_establecimiento]);
    $establecimiento_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$establecimiento_actual) {
        $_SESSION['error'] = "Establecimiento no encontrado.";
        header("Location: ../../dashboard_admin_bd.php?vista=estudiantes");
        exit;
    }

    // Asignar valores iniciales
    $nombre = $establecimiento_actual['Nombre'];
    $id_direccion = $establecimiento_actual['Id_Direccion'];
    $direccion = $establecimiento_actual['Direccion'];
    $comuna_id = $establecimiento_actual['Id_Comuna'];
    $region_id = $establecimiento_actual['Id_Region'];

} catch (PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}


// --- 2. PROCESAR EL FORMULARIO DE EDICIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $region_id = $_POST['region_id']; 
    $comuna_id = $_POST['comuna_id'];

    if (empty($nombre)) { $errores[] = "El nombre es obligatorio."; }
    if (empty($direccion)) { $errores[] = "La dirección es obligatoria."; }
    if (empty($region_id)) { $errores[] = "La región es obligatoria."; }
    if (empty($comuna_id)) { $errores[] = "La comuna es obligatoria."; }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // Actualizar Dirección
            $stmt_dir = $pdo->prepare("UPDATE Direccion SET Id_Comuna = ?, Direccion = ? WHERE Id = ?");
            $stmt_dir->execute([$comuna_id, $direccion, $id_direccion]);

            // Actualizar Establecimiento
            $stmt_est = $pdo->prepare("UPDATE Establecimiento SET Nombre = ? WHERE Id = ?");
            $stmt_est->execute([$nombre, $id_establecimiento]);

            $pdo->commit();

            $_SESSION['success_message'] = "Establecimiento actualizado correctamente.";
            // Redirigir de vuelta a la lista de cursos de este establecimiento o a la lista general
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// --- 3. OBTENER DATOS PARA LOS SELECTS (IGUAL QUE EN CREATE) ---
$regiones = $pdo->query("SELECT Id, Region FROM Region ORDER BY Region")->fetchAll(PDO::FETCH_ASSOC);
// Traemos TODAS las comunas para filtrarlas con JS
$comunas = $pdo->query("SELECT Id, Comuna, Id_Region FROM Comuna ORDER BY Comuna")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Establecimiento</title>
    <link rel="stylesheet" href="../../css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        
        <aside class="sidebar">
             <div class="sidebar-header">
                <h2>DAEM NutriMonitor</h2>
            </div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-user">
                    <?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?>
                </div>
            </header>

            <section class="content-body">
                <div class="content-container">
                    <h1><i class="fa-solid fa-pencil"></i> Editar Establecimiento</h1>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <ul>
                                <?php foreach ($errores as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="edit.php?id=<?php echo $id_establecimiento; ?>" method="POST" class="crud-form">
                        
                        <div class="form-group">
                            <label for="nombre">Nombre del Establecimiento:</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="direccion">Dirección:</label>
                            <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($direccion); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="region_id">Región:</label>
                            <select id="region_id" name="region_id" required onchange="filtrarComunas()">
                                <option value="">Seleccione una región</option>
                                <?php foreach ($regiones as $region): ?>
                                    <option value="<?php echo $region['Id']; ?>" <?php echo ($region['Id'] == $region_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($region['Region']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="comuna_id">Comuna:</label>
                            <select id="comuna_id" name="comuna_id" required>
                                <!-- Se llenará con JS -->
                            </select>
                        </div>

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn-create" style="cursor:pointer; background-color: #ffc107; color: #000;"><i class="fa-solid fa-save"></i> Actualizar</button>
                            <a href="../dashboard_admin_bd.php?vista=estudiantes" class="btn-delete" style="padding: 8px 15px; margin-left: 10px;">Cancelar</a>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Pasamos las comunas de PHP a JS
        const todasLasComunas = <?php echo json_encode($comunas); ?>;
        
        // El ID de la comuna que tiene guardado este establecimiento
        const comunaActualId = "<?php echo $comuna_id; ?>";

        function filtrarComunas() {
            const regionSelect = document.getElementById('region_id');
            const comunaSelect = document.getElementById('comuna_id');
            const regionId = regionSelect.value;

            // Guardar selección actual si existe (para cuando el usuario cambia región y vuelve)
            const seleccionPrevia = comunaSelect.value;

            comunaSelect.innerHTML = '<option value="">Seleccione una comuna</option>';

            if (regionId) {
                comunaSelect.disabled = false;
                const comunasFiltradas = todasLasComunas.filter(c => c.Id_Region == regionId);

                comunasFiltradas.forEach(comuna => {
                    const option = document.createElement('option');
                    option.value = comuna.Id;
                    option.textContent = comuna.Comuna;
                    
                    // LOGICA DE SELECCIÓN:
                    // 1. Si es la primera carga (o refresco por error), seleccionamos la comuna original de la BD.
                    // 2. Si el usuario está jugando con el select, mantenemos su selección si es válida para la región.
                    if (comuna.Id == comunaActualId || comuna.Id == seleccionPrevia) {
                        option.selected = true;
                    }
                    
                    comunaSelect.appendChild(option);
                });
            } else {
                comunaSelect.disabled = true;
            }
        }

        // Ejecutar inmediatamente al cargar para pre-llenar el select de comunas
        // basado en la región que ya viene seleccionada por PHP
        document.addEventListener('DOMContentLoaded', filtrarComunas);
    </script>
</body>
</html>