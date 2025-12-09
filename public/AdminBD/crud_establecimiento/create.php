<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php"); exit;
}

$nombre = $direccion = $region_id = $comuna_id = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $region_id = $_POST['region_id'];
    $comuna_id = $_POST['comuna_id'];

    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($direccion)) $errores[] = "La dirección es obligatoria.";
    if (empty($region_id)) $errores[] = "La región es obligatoria.";
    if (empty($comuna_id)) $errores[] = "La comuna es obligatoria.";

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            $stmt_dir = $pdo->prepare("INSERT INTO Direccion (Id_Comuna, Direccion) VALUES (?, ?)");
            $stmt_dir->execute([$comuna_id, $direccion]);
            $id_direccion = $pdo->lastInsertId();

            $stmt_est = $pdo->prepare("INSERT INTO Establecimiento (Id_Direccion, Nombre) VALUES (?, ?)");
            $stmt_est->execute([$id_direccion, $nombre]);
            $pdo->commit();

            $_SESSION['success_message'] = "Establecimiento creado exitosamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes"); exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = "Error BD: " . $e->getMessage();
        }
    }
}

$regiones = $pdo->query("SELECT Id, Region FROM Region ORDER BY Region")->fetchAll(PDO::FETCH_ASSOC);
$comunas = $pdo->query("SELECT Id, Comuna, Id_Region FROM Comuna ORDER BY Comuna")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Establecimiento</title>
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="../../dashboard_admin_bd.php?vista=estudiantes" class="btn-header-back">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">Admin BD</div>
        </div>
        <div class="header-user-section">
            <div class="user-info"><span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></span></div>
            <a href="../../logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 800px; margin: 0 auto;">
            <h1 style="border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:20px;">
                <i class="fa-solid fa-school"></i> Nuevo Establecimiento
            </h1>

            <?php if (!empty($errores)): ?>
                <div class="mensaje error"><ul><?php foreach ($errores as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="POST" class="crud-form">
                <div class="form-group">
                    <label>Nombre del Establecimiento:</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required maxlength="200">
                </div>
                <div class="form-group">
                    <label>Dirección:</label>
                    <input type="text" name="direccion" value="<?php echo htmlspecialchars($direccion); ?>" required maxlength="200">
                </div>
                <div style="display:flex; gap:20px;">
                    <div class="form-group" style="flex:1;">
                        <label>Región:</label>
                        <select id="region_id" name="region_id" required onchange="filtrarComunas()">
                            <option value="">Seleccione...</option>
                            <?php foreach ($regiones as $r): ?>
                                <option value="<?php echo $r['Id']; ?>" <?php echo ($r['Id'] == $region_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['Region']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Comuna:</label>
                        <select id="comuna_id" name="comuna_id" required disabled><option value="">Seleccione Región primero</option></select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:20px;">
                    <button type="submit" class="btn-create"><i class="fa-solid fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
        <footer class="main-footer">&copy; <?php echo date("Y"); ?> NutriData.</footer>
    </main>

    <script>
        const todasLasComunas = <?php echo json_encode($comunas); ?>;
        const comunaPreseleccionada = "<?php echo $comuna_id; ?>";

        function filtrarComunas() {
            const regionId = document.getElementById('region_id').value;
            const comunaSelect = document.getElementById('comuna_id');
            comunaSelect.innerHTML = '<option value="">Seleccione una comuna</option>';

            if (regionId) {
                comunaSelect.disabled = false;
                const filtradas = todasLasComunas.filter(c => c.Id_Region == regionId);
                filtradas.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.Id;
                    opt.textContent = c.Comuna;
                    if (c.Id == comunaPreseleccionada) opt.selected = true;
                    comunaSelect.appendChild(opt);
                });
            } else {
                comunaSelect.disabled = true;
            }
        }
        document.addEventListener('DOMContentLoaded', filtrarComunas);
    </script>
</body>
</html>