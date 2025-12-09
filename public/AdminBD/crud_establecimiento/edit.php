<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php"); exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: ../../dashboard_admin_bd.php?vista=estudiantes"); exit; }

$stmt = $pdo->prepare("SELECT e.Nombre, e.Id_Direccion, d.Direccion, d.Id_Comuna, c.Id_Region FROM Establecimiento e JOIN Direccion d ON e.Id_Direccion = d.Id JOIN Comuna c ON d.Id_Comuna = c.Id WHERE e.Id = ?");
$stmt->execute([$id]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$datos) die("No encontrado");

$nombre = $datos['Nombre'];
$direccion = $datos['Direccion'];
$region_id = $datos['Id_Region'];
$comuna_id = $datos['Id_Comuna'];
$id_dir = $datos['Id_Direccion'];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $region_id = $_POST['region_id'];
    $comuna_id = $_POST['comuna_id'];

    if (empty($nombre) || empty($direccion)) $errores[] = "Datos incompletos.";

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE Direccion SET Id_Comuna = ?, Direccion = ? WHERE Id = ?")->execute([$comuna_id, $direccion, $id_dir]);
            $pdo->prepare("UPDATE Establecimiento SET Nombre = ? WHERE Id = ?")->execute([$nombre, $id]);
            $pdo->commit();
            $_SESSION['success_message'] = "Actualizado correctamente.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes"); exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = "Error: " . $e->getMessage();
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
    <title>Editar Establecimiento</title>
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="../../dashboard_admin_bd.php?vista=estudiantes" class="btn-header-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">Admin BD</div>
        </div>
        <div class="header-user-section">
            <div class="user-info"><span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></span></div>
            <a href="../../logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 800px; margin: 0 auto;">
            <h1 style="border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:20px;">Editar Establecimiento</h1>
            <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

            <form method="POST" class="crud-form">
                <div class="form-group"><label>Nombre:</label><input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required></div>
                <div class="form-group"><label>Dirección:</label><input type="text" name="direccion" value="<?php echo htmlspecialchars($direccion); ?>" required></div>
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
                        <select id="comuna_id" name="comuna_id" required></select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:20px;">
                    <button type="submit" class="btn-create" style="background:#ffc107; color:black;"><i class="fa-solid fa-save"></i> Actualizar</button>
                </div>
            </form>
        </div>
        <footer class="main-footer">&copy; <?php echo date("Y"); ?> NutriData.</footer>
    </main>

    <script>
        const todasLasComunas = <?php echo json_encode($comunas); ?>;
        const comunaActual = "<?php echo $comuna_id; ?>";
        function filtrarComunas() {
            const regId = document.getElementById('region_id').value;
            const comSelect = document.getElementById('comuna_id');
            comSelect.innerHTML = '<option value="">Seleccione...</option>';
            if(regId) {
                comSelect.disabled = false;
                todasLasComunas.filter(c => c.Id_Region == regId).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.Id; opt.textContent = c.Comuna;
                    if(c.Id == comunaActual) opt.selected = true;
                    comSelect.appendChild(opt);
                });
            } else { comSelect.disabled = true; }
        }
        document.addEventListener('DOMContentLoaded', filtrarComunas);
    </script>
</body>
</html>