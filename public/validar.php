<?php
// 1. Iniciar la sesión
session_start();

// 2. Incluir y obtener la conexión PDO
require_once __DIR__ . '/../src/config/db.php'; 
$pdo = getConnection();

// 3. Recibir los datos del formulario
$rut = $_POST['rut'];
$contrasena_ingresada = $_POST['contrasena'];

// 4. Preparar la consulta SQL (La consulta SQL es la misma)
$sql = "SELECT 
            u.Id, 
            u.Nombre, 
            u.Apellido, 
            u.Contraseña, 
            u.Estado, 
            r.Nombre AS NombreRol
        FROM Usuario u
        JOIN Rol r ON u.Id_Rol = r.Id
        WHERE u.Rut = ?";

// --- CAMBIOS DE MYSQLI A PDO ---

// 5. Preparar la consulta usando PDO
$stmt = $pdo->prepare($sql);

// 6. Ejecutar la consulta, pasando los datos en un array
$stmt->execute([$rut]);

// 7. Obtener el usuario
// fetch() devuelve al usuario, o 'false' si no se encontró
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// 8. Verificar si se encontró un usuario
if ($usuario) {
    
    // 9. Verificar la contraseña (ahora seguro)
    if (password_verify($contrasena_ingresada, $usuario['Contraseña'])) {
        
        // 10. Verificar si el usuario está ACTIVO
        if ($usuario['Estado'] == 1) {
            
            // ¡ÉXITO! Guardamos los datos en la sesión
            $_SESSION['user_id'] = $usuario['Id'];
            $_SESSION['user_nombre'] = $usuario['Nombre'] . " " . $usuario['Apellido'];
            $_SESSION['user_rol'] = $usuario['NombreRol'];

            // 11. Redirigir según el ROL
            switch ($usuario['NombreRol']) {
                case 'administradorBD':
                    header("Location: dashboard_admin_bd.php");
                    exit;
                case 'administradorDAEM':
                    header("Location: dashboard_admin_daem.php");
                    exit;
                case 'profesor':
                    header("Location: dashboard_profesor.php");
                    exit;
                default:
                    // Rol no reconocido
                    $_SESSION['error'] = "Rol de usuario no válido.";
                    header("Location: login.php");
                    exit;
            }

        } else {
            // Error: Usuario inactivo
            $_SESSION['error'] = "Su cuenta está inactiva. Contacte al administrador.";
            header("Location: login.php");
            exit;
        }

    } else {
        // Error: Contraseña incorrecta
        $_SESSION['error'] = "RUT o contraseña incorrectos.";
        header("Location: login.php");
        exit;
    }

} else {
    // Error: Usuario no existe (fetch devolvió false)
    $_SESSION['error'] = "RUT o contraseña incorrectos.";
    header("Location: login.php");
    exit;
}

// Con PDO, no es estrictamente necesario cerrar la conexión ($pdo = null;)
// al final del script, ya que PHP lo maneja automáticamente.
?>