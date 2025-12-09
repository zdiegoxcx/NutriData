<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php"); exit;
}

$id_est = $_GET['id_establecimiento'] ?? null;
if (!$id_est) { header("Location: ../../dashboard_admin_bd.php"); exit; }

$errores = []; $grado = $letra = $profesor_id = '';
$profesores = $pdo->query("SELECT u.Id, u.Nombre, u.Apellido, u.Rut FROM Usuario u JOIN Rol r ON u.Id_Rol = r.Id WHERE r.Nombre = 'profesor' AND u.Estado = 1 ORDER BY u.Apellido")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grado = $_POST['grado']; $letra = $_POST['letra']; $profesor_id = $_POST['profesor_id'];
    if (empty($grado) || empty($letra) || empty($profesor_id)) $errores[] = "Datos incompletos.";
    
    $nombre = "$grado $letra";
    $check = $pdo->prepare("SELECT Id FROM Curso WHERE Id_Establecimiento = ? AND Nombre = ? AND Estado = 1");
    $check->execute([$id_est, $nombre]);
    if ($check->rowCount() > 0) $errores[] = "El curso ya existe.";

    if (empty($errores)) {
        try {
            $pdo->prepare("INSERT INTO Curso (Id_Establecimiento, Nombre, Id_Profesor, Estado) VALUES (?, ?, ?, 1)")->execute([$id_est, $nombre, $profesor_id]);
            $_SESSION['success_message'] = "Curso $nombre creado.";
            header("Location: ../../dashboard_admin_bd.php?vista=estudiantes&id_establecimiento=$id_est"); exit;
        } catch (PDOException $e) { $errores[] = "Error: " . $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Curso</title>
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
            <h1 style="border-bottom:1px solid #eee; padding-bottom:10px;">Nuevo Curso</h1>
            <?php if ($errores): ?><div class="mensaje error"><?php echo implode('<br>', $errores); ?></div><?php endif; ?>

            <form method="POST" class="crud-form">
                <div style="display:flex; gap:20px; margin-bottom:20px;">
                    <div class="form-group" style="flex:2;">
                        <label>Grado:</label>
                        <select name="grado" required>
                            <option value="">Seleccione...</option>
                            <optgroup label="Pre-Básica"><option value="Pre-Kinder">Pre-Kinder</option><option value="Kinder">Kinder</option></optgroup>
                            <optgroup label="Básica"><?php for($i=1;$i<=8;$i++) echo "<option value='{$i}° Básico'>{$i}° Básico</option>"; ?></optgroup>
                            <optgroup label="Media"><?php for($i=1;$i<=4;$i++) echo "<option value='{$i}° Medio'>{$i}° Medio</option>"; ?></optgroup>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Letra:</label>
                        <select name="letra" required>
                            <option value="">...</option>
                            <?php foreach(range('A','F') as $l) echo "<option value='$l'>$l</option>"; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="position:relative;">
                    <label>Profesor Jefe:</label>
                    <input type="hidden" id="profesor_id" name="profesor_id" required>
                    <input type="text" id="search_input" class="search-input" placeholder="Buscar profesor..." autocomplete="off">
                    <div id="profesor_list" class="profesor-list"></div>
                    <div id="selected_display" style="display:none; margin-top:10px; background:#e7f1ff; padding:10px; border-radius:6px; align-items:center; justify-content:space-between;">
                        <span id="selected_name"></span>
                        <button type="button" onclick="clearSelection()" style="border:none; background:none; cursor:pointer; color:red;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <div class="form-actions" style="margin-top:30px;">
                    <button type="submit" class="btn-create" style="width:100%;">Crear Curso</button>
                </div>
            </form>
        </div>
        <footer class="main-footer">&copy; <?php echo date("Y"); ?> NutriData.</footer>
    </main>

    <script>
        const profes = <?php echo json_encode($profesores); ?>;
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
                        document.getElementById('selected_name').innerText = `${p.Nombre} ${p.Apellido}`;
                        display.style.display = 'flex';
                        search.style.display = 'none';
                        list.style.display = 'none';
                    };
                    list.appendChild(div);
                });
            } else { list.style.display = 'none'; }
        });

        function clearSelection() {
            hidden.value = ''; display.style.display = 'none'; search.style.display = 'block'; search.value = ''; search.focus();
        }
    </script>
</body>
</html>