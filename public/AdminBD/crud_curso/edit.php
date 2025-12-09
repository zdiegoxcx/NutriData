<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php"); exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: ../../dashboard_admin_bd.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM Curso WHERE Id = ?");
$stmt->execute([$id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$curso) die("Curso no encontrado.");

$id_est = $curso['Id_Establecimiento'];
$partes = explode(' ', $curso['Nombre']);
$letra_sel = array_pop($partes);
$grado_sel = implode(' ', $partes);
$profes = $pdo->query("SELECT u.Id, u.Nombre, u.Apellido, u.Rut FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id WHERE r.Nombre = 'profesor' AND u.Estado = 1 ORDER BY u.Apellido")->fetchAll(PDO::FETCH_ASSOC);

$profe_actual = null;
foreach($profes as $p) { if($p['Id'] == $curso['Id_Profesor']) { $profe_actual = $p; break; } }

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grado = $_POST['grado']; $letra = $_POST['letra']; $profesor_id = $_POST['profesor_id'];
    $nombre = "$grado $letra";
    
    $check = $pdo->prepare("SELECT Id FROM Curso WHERE Id_Establecimiento = ? AND Nombre = ? AND Estado = 1 AND Id != ?");
    $check->execute([$id_est, $nombre, $id]);
    if ($check->rowCount() > 0) $errores[] = "Ya existe este curso.";

    if (empty($errores)) {
        $pdo->prepare("UPDATE Curso SET Nombre = ?, Id_Profesor = ? WHERE Id = ?")->execute([$nombre, $profesor_id, $id]);
        $_SESSION['success_message'] = "Curso actualizado.";
        header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_est"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Curso</title>
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>.profesor-list { max-height:200px; overflow-y:auto; border:1px solid #ddd; display:none; position:absolute; background:white; width:100%; z-index:10; } .profesor-item { padding:10px; cursor:pointer; border-bottom:1px solid #eee; } .profesor-item:hover { background:#f0f8ff; }</style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=<?php echo $id_est; ?>" class="btn-header-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            <div class="brand-logo" style="margin-left:10px; font-size:1.1rem; color:#333;">Admin BD</div>
        </div>
        <div class="header-user-section">
            <div class="user-info"><span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></span></div>
            <a href="../../logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-container" style="max-width: 700px; margin: 0 auto;">
            <h1 style="border-bottom:1px solid #eee; padding-bottom:10px;">Editar Curso</h1>
            <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

            <form method="POST" class="crud-form">
                <div style="display:flex; gap:20px; margin-bottom:20px;">
                    <div class="form-group" style="flex:2;">
                        <label>Grado:</label>
                        <select name="grado" required>
                            <optgroup label="Pre-Básica"><option value="Pre-Kinder" <?php if($grado_sel=='Pre-Kinder') echo 'selected'; ?>>Pre-Kinder</option><option value="Kinder" <?php if($grado_sel=='Kinder') echo 'selected'; ?>>Kinder</option></optgroup>
                            <optgroup label="Básica"><?php for($i=1;$i<=8;$i++) { $v="$i° Básico"; echo "<option value='$v' ".($grado_sel==$v?'selected':'').">$v</option>"; } ?></optgroup>
                            <optgroup label="Media"><?php for($i=1;$i<=4;$i++) { $v="$i° Medio"; echo "<option value='$v' ".($grado_sel==$v?'selected':'').">$v</option>"; } ?></optgroup>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Letra:</label>
                        <select name="letra" required>
                            <?php foreach(range('A','F') as $l) echo "<option value='$l' ".($letra_sel==$l?'selected':'').">$l</option>"; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="position:relative;">
                    <label>Profesor Jefe:</label>
                    <input type="hidden" id="profesor_id" name="profesor_id" value="<?php echo $curso['Id_Profesor']; ?>" required>
                    <input type="text" id="search_input" class="search-input" placeholder="Buscar profesor..." style="<?php echo $profe_actual?'display:none':'block'; ?>">
                    <div id="profesor_list" class="profesor-list"></div>
                    <div id="selected_display" style="display:<?php echo $profe_actual?'flex':'none'; ?>; margin-top:10px; background:#e7f1ff; padding:10px; border-radius:6px; align-items:center; justify-content:space-between;">
                        <span id="selected_name">
                            <?php if($profe_actual) echo "<strong>{$profe_actual['Nombre']} {$profe_actual['Apellido']}</strong> ({$profe_actual['Rut']})"; ?>
                        </span>
                        <button type="button" onclick="clearSelection()" style="border:none; background:none; cursor:pointer; color:red;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <div class="form-actions" style="margin-top:30px;">
                    <button type="submit" class="btn-create" style="width:100%; background:#ffc107; color:black;">Actualizar</button>
                </div>
            </form>
        </div>
        <footer class="main-footer">&copy; <?php echo date("Y"); ?> NutriData.</footer>
    </main>

    <script>
        const profes = <?php echo json_encode($profes); ?>;
        const search = document.getElementById('search_input');
        const list = document.getElementById('profesor_list');
        const hidden = document.getElementById('profesor_id');
        const display = document.getElementById('selected_display');

        search.addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            list.innerHTML = '';
            if (term.length < 1) { list.style.display = 'none'; return; }
            const filtered = profes.filter(p => p.Nombre.toLowerCase().includes(term) || p.Apellido.toLowerCase().includes(term) || p.Rut.includes(term));
            if (filtered.length > 0) {
                list.style.display = 'block';
                filtered.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'profesor-item';
                    div.innerHTML = `<strong>${p.Nombre} ${p.Apellido}</strong> (${p.Rut})`;
                    div.onclick = () => {
                        hidden.value = p.Id;
                        document.getElementById('selected_name').innerHTML = `<strong>${p.Nombre} ${p.Apellido}</strong> (${p.Rut})`;
                        display.style.display = 'flex'; search.style.display = 'none'; list.style.display = 'none';
                    };
                    list.appendChild(div);
                });
            } else { list.style.display = 'none'; }
        });

        function clearSelection() { hidden.value = ''; display.style.display = 'none'; search.style.display = 'block'; search.value = ''; search.focus(); }
    </script>
</body>
</html>