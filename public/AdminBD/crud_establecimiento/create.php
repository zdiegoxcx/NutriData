<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

// --- GUARDIÁN DE LA PÁGINA ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$nombre = $direccion = $region_id = $comuna_id = '';
$errores = [];

// --- PROCESAR EL FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $region_id = $_POST['region_id']; // Solo para mantener la selección en caso de error
    $comuna_id = $_POST['comuna_id'];

    if (empty($nombre)) { $errores[] = "El nombre del establecimiento es obligatorio."; }
    if (empty($direccion)) { $errores[] = "La dirección es obligatoria."; }
    if (empty($region_id)) { $errores[] = "La región es obligatoria."; }
    if (empty($comuna_id)) { $errores[] = "La comuna es obligatoria."; }

    if (empty($errores)) {
        try {
            // 1. Iniciar transacción para asegurar integridad
            $pdo->beginTransaction();

            // 2. Insertar la dirección
            $stmt_dir = $pdo->prepare("INSERT INTO Direccion (Id_Comuna, Direccion) VALUES (?, ?)");
            $stmt_dir->execute([$comuna_id, $direccion]);
            $id_direccion = $pdo->lastInsertId();

            // 3. Insertar el establecimiento
            $stmt_est = $pdo->prepare("INSERT INTO Establecimiento (Id_Direccion, Nombre) VALUES (?, ?)");
            $stmt_est->execute([$id_direccion, $nombre]);

            // 4. Confirmar transacción
            $pdo->commit();

            $_SESSION['success_message'] = "Establecimiento creado exitosamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack(); // Deshacer cambios si algo falla
            $errores[] = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

// --- OBTENER DATOS PARA LOS SELECTS ---
// 1. Obtener todas las Regiones
$regiones = $pdo->query("SELECT Id, Region FROM Region ORDER BY Region")->fetchAll(PDO::FETCH_ASSOC);

// 2. Obtener todas las Comunas (con su Id_Region para filtrar en JS)
// Usamos JSON_ENCODE para pasarlas a JavaScript más abajo
$comunas = $pdo->query("SELECT Id, Comuna, Id_Region FROM Comuna ORDER BY Comuna")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Establecimiento</title>
    <!-- Ajusta la ruta del CSS según donde tengas tu archivo de estilos -->
    <link rel="stylesheet" href="../../css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        
        <!-- Aquí iría tu sidebar include si lo separas -->
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
                    <h1><i class="fa-solid fa-school"></i> Crear Nuevo Establecimiento</h1>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <ul>
                                <?php foreach ($errores as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="create.php" method="POST" class="crud-form">
                        
                        <div class="form-group">
                            <label for="nombre">Nombre del Establecimiento:</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" placeholder="Ej: Liceo Bicentenario" required maxlength="200">
                        </div>

                        <div class="form-group">
                            <label for="direccion">Dirección:</label>
                            <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($direccion); ?>" placeholder="Calle y número" required maxlength="200">
                        </div>

                        <!-- SELECT DE REGIÓN -->
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

                        <!-- SELECT DE COMUNA (Se llena con JS) -->
                        <div class="form-group">
                            <label for="comuna_id">Comuna:</label>
                            <select id="comuna_id" name="comuna_id" required disabled>
                                <option value="">Primero seleccione una región</option>
                                <!-- Las opciones se llenarán dinámicamente -->
                            </select>
                        </div>

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn-create" style="cursor:pointer;"><i class="fa-solid fa-save"></i> Guardar</button>
                            
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <!-- LÓGICA JAVASCRIPT PARA EL FILTRADO -->
    <script>
        // 1. Obtener todas las comunas desde PHP como un objeto JSON
        const todasLasComunas = <?php echo json_encode($comunas); ?>;
        
        // 2. Variable para mantener la comuna seleccionada si hubo error en el formulario
        const comunaPreseleccionada = "<?php echo $comuna_id; ?>";

        function filtrarComunas() {
            const regionSelect = document.getElementById('region_id');
            const comunaSelect = document.getElementById('comuna_id');
            const regionId = regionSelect.value;

            // Limpiar select de comunas
            comunaSelect.innerHTML = '<option value="">Seleccione una comuna</option>';

            if (regionId) {
                // Habilitar el select
                comunaSelect.disabled = false;

                // Filtrar las comunas que coinciden con la región seleccionada
                const comunasFiltradas = todasLasComunas.filter(c => c.Id_Region == regionId);

                // Agregar las opciones al select
                comunasFiltradas.forEach(comuna => {
                    const option = document.createElement('option');
                    option.value = comuna.Id;
                    option.textContent = comuna.Comuna;
                    
                    // Si había una comuna seleccionada previamente (por error de validación), seleccionarla
                    if (comuna.Id == comunaPreseleccionada) {
                        option.selected = true;
                    }
                    
                    comunaSelect.appendChild(option);
                });
            } else {
                // Si no hay región, deshabilitar y mostrar mensaje
                comunaSelect.disabled = true;
                comunaSelect.innerHTML = '<option value="">Primero seleccione una región</option>';
            }
        }

        // Ejecutar al cargar la página por si hay datos prellenados (ej: tras un error de validación)
        document.addEventListener('DOMContentLoaded', filtrarComunas);
    </script>
</body>
</html>