<?php
session_start();
require_once __DIR__ . '/../../../src/config/db.php';
$pdo = getConnection();

// --- VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'administradorBD') {
    header("Location: ../../login.php");
    exit;
}

$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) {
    header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
    exit;
}

$errores = [];

// --- 1. OBTENER DATOS ACTUALES ---
try {
    $stmt = $pdo->prepare("SELECT * FROM Usuario WHERE Id = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $_SESSION['error'] = "Usuario no encontrado.";
        header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
        exit;
    }
} catch (PDOException $e) {
    die("Error al cargar usuario: " . $e->getMessage());
}

// Determinar si el usuario está activo o inactivo para bloquear campos
$es_activo = ($usuario['Estado'] == 1);
$readonly_attr = $es_activo ? '' : 'disabled'; // Atributo HTML para inputs

// --- 2. OBTENER ROLES ---
$roles = $pdo->query("SELECT Id, Nombre FROM Rol ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

// --- 3. PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Si el usuario ya está inactivo, NO procesamos ninguna acción POST (seguridad extra)
    if (!$es_activo) {
        $_SESSION['error'] = "No se pueden modificar datos de un usuario inactivo.";
        header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
        exit;
    }

    // A. LÓGICA PARA DESACTIVAR (ELIMINAR)
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $motivo = trim($_POST['motivo_eliminacion']);
        
        if (empty($motivo)) {
            $errores[] = "Debe especificar un motivo para desactivar al usuario.";
        } else {
            try {
                // Cambiamos Estado a 0
                $sql_del = "UPDATE Usuario SET Estado = 0, FechaEliminacion = NOW(), MotivoEliminacion = ? WHERE Id = ?";
                $stmt_del = $pdo->prepare($sql_del);
                $stmt_del->execute([$motivo, $id_usuario]);

                $_SESSION['success_message'] = "El usuario ha sido desactivado correctamente.";
                header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
                exit;
            } catch (PDOException $e) {
                $errores[] = "Error al desactivar: " . $e->getMessage();
            }
        }
    } 
    // B. LÓGICA PARA ACTUALIZAR DATOS
    else {
        $rut = trim($_POST['rut']);
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);
        $nueva_contrasena = $_POST['contrasena'];
        $rol_id = $_POST['rol_id'];

        if (empty($rut)) { $errores[] = "El RUT es obligatorio."; }
        if (empty($nombre)) { $errores[] = "El nombre es obligatorio."; }
        if (empty($rol_id)) { $errores[] = "Debe asignar un rol."; }

        if (empty($errores)) {
            try {
                // Nota: Ya no actualizamos el campo 'Estado' aquí, se mantiene el actual (1)
                if (!empty($nueva_contrasena)) {
                    $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=?, Contraseña=? WHERE Id=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $nueva_contrasena, $id_usuario]);
                } else {
                    $sql = "UPDATE Usuario SET Id_Rol=?, Rut=?, Nombre=?, Apellido=?, Email=?, Telefono=? WHERE Id=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$rol_id, $rut, $nombre, $apellido, $email, $telefono, $id_usuario]);
                }

                $_SESSION['success_message'] = "Usuario actualizado correctamente.";
                header("Location: ../../dashboard_admin_bd.php?vista=usuarios");
                exit;

            } catch (PDOException $e) {
                $errores[] = "Error al actualizar: " . $e->getMessage();
            }
        }
    }
} else {
    // Pre-carga de datos
    $rut = $usuario['Rut'];
    $nombre = $usuario['Nombre'];
    $apellido = $usuario['Apellido'];
    $email = $usuario['Email'];
    $telefono = $usuario['Telefono'];
    $rol_id = $usuario['Id_Rol'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Input deshabilitado se ve más claro */
        input:disabled, select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
            color: #6c757d;
        }

        /* === ESTILOS DEL MODAL (ESTILO ALERTA/ROJO) === */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        /* Recuperamos el estilo de la "danger-zone" antigua */
        .modal-content {
            background: #fff5f5; /* Fondo rojizo claro */
            padding: 25px;
            border: 1px solid #feb2b2; /* Borde rojo suave */
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            position: relative;
            animation: fadeIn 0.3s;
            color: #742a2a; /* Texto rojo oscuro */
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #c53030;
        }
        .close-btn:hover { color: #9b2c2c; }

        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DAEM NutriMonitor</h2>
            </div>
            <nav class="sidebar-nav">
                 <a href="../../dashboard_admin_bd.php?vista=usuarios" class="nav-item active"><i class="fa-solid fa-arrow-left"></i> Volver</a>
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
                    
                    <?php if ($es_activo): ?>
                        <h1><i class="fa-solid fa-user-pen"></i> Editar Usuario</h1>
                    <?php else: ?>
                        <h1 style="color: #6c757d;"><i class="fa-solid fa-user-lock"></i> Usuario Inactivo (Solo Lectura)</h1>
                    <?php endif; ?>

                    <?php if (!empty($errores)): ?>
                        <div class="mensaje error">
                            <ul>
                                <?php foreach ($errores as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="edit.php?id=<?php echo $id_usuario; ?>" method="POST" class="crud-form">
                        
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="rut">RUT:</label>
                                <input type="text" id="rut" name="rut" value="<?php echo htmlspecialchars($rut); ?>" required <?php echo $readonly_attr; ?>>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="rol_id">Rol:</label>
                                <select id="rol_id" name="rol_id" required <?php echo $readonly_attr; ?>>
                                    <option value="">Seleccione un rol</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?php echo $rol['Id']; ?>" <?php echo ($rol['Id'] == $rol_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rol['Nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="nombre">Nombre:</label>
                                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required <?php echo $readonly_attr; ?>>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="apellido">Apellido:</label>
                                <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" required <?php echo $readonly_attr; ?>>
                            </div>
                        </div>

                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" <?php echo $readonly_attr; ?>>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="telefono">Teléfono:</label>
                                <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>" <?php echo $readonly_attr; ?>>
                            </div>
                        </div>

                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="contrasena">Contraseña:</label>
                                <input type="password" id="contrasena" name="contrasena" placeholder="Dejar en blanco para mantener" <?php echo $readonly_attr; ?>>
                            </div>
                            
                            <div class="form-group" style="flex: 1;">
                                <label>Estado Actual:</label>
                                <?php if ($es_activo): ?>
                                    <div style="padding: 10px; background: #d1e7dd; color: #0f5132; border-radius: 4px; font-weight: bold;">
                                        <i class="fa-solid fa-check-circle"></i> Activo
                                    </div>
                                <?php else: ?>
                                    <div style="padding: 10px; background: #f8d7da; color: #842029; border-radius: 4px; font-weight: bold;">
                                        <i class="fa-solid fa-ban"></i> Inactivo
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 30px; display: flex; gap: 15px; align-items: center;">
                            
                            <?php if ($es_activo): ?>
                                <button type="submit" class="btn-create" style="background-color: #ffc107; color: #000; cursor: pointer; border:none; padding: 10px 20px;">
                                    <i class="fa-solid fa-save"></i> Actualizar Datos
                                </button>

                                <button type="button" onclick="abrirModal()" class="btn-delete" style="cursor: pointer; border:none; padding: 10px 20px;">
                                    <i class="fa-solid fa-ban"></i> Desactivar Usuario
                                </button>
                            
                            <?php else: ?>
                                <p style="color: #dc3545; font-style: italic;">
                                    <i class="fa-solid fa-lock"></i> Este usuario fue desactivado el <?php echo $usuario['FechaEliminacion']; ?>. 
                                    Motivo: "<?php echo htmlspecialchars($usuario['MotivoEliminacion']); ?>".
                                </p>
                            <?php endif; ?>

                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <div id="modalDesactivar" class="modal-overlay">
        <div class="modal-content">
            <span class="close-btn" onclick="cerrarModal()">&times;</span>
            
            <h2 style="color: #c53030; margin-bottom: 15px;">
                <i class="fa-solid fa-triangle-exclamation"></i> Confirmar Desactivación
            </h2>
            
            <p style="margin-bottom: 20px;">
                ¿Estás seguro? Al desactivar al usuario, este <strong>perderá el acceso al sistema</strong> inmediatamente y sus datos quedarán congelados.
            </p>

            <form action="edit.php?id=<?php echo $id_usuario; ?>" method="POST">
                <input type="hidden" name="accion" value="eliminar">
                
                <div class="form-group">
                    <label for="motivo_eliminacion" style="font-weight: bold; color: #742a2a;">Motivo (Obligatorio):</label>
                    <textarea id="motivo_eliminacion" name="motivo_eliminacion" rows="3" 
                              placeholder="Ej: Fin de contrato..." required 
                              style="width: 100%; padding: 10px; border: 1px solid #feb2b2; background: #fff; border-radius: 4px; resize: vertical;"></textarea>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="cerrarModal()" style="padding: 8px 15px; background: #e2e8f0; border: none; cursor: pointer; margin-right: 10px; color: #333;">Cancelar</button>
                    <button type="submit" class="btn-delete" style="padding: 8px 20px; cursor: pointer;">Confirmar y Desactivar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modalDesactivar');

        function abrirModal() {
            modal.style.display = 'flex';
        }

        function cerrarModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                cerrarModal();
            }
        }
    </script>
</body>
</html>