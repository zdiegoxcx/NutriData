<?php
// public/generar_usuarios_masivos.php
require_once __DIR__ . '/../src/config/db.php';

// Aumentar tiempo de ejecución por si acaso
set_time_limit(300);

// Función para calcular Dígito Verificador (RUT Válido)
function calcularRut($numero) {
    $i = 2;
    $suma = 0;
    foreach (array_reverse(str_split($numero)) as $v) {
        if ($i == 8) $i = 2;
        $suma += $v * $i;
        $i++;
    }
    $dvr = 11 - ($suma % 11);
    if ($dvr == 11) $dv = '0';
    elseif ($dvr == 10) $dv = 'K';
    else $dv = $dvr;
    return number_format($numero, 0, '', '.') . "-" . $dv;
}

// Datos semilla
$nombres = ['Juan', 'Maria', 'Pedro', 'Ana', 'Luis', 'Claudia', 'Jose', 'Francisca', 'Diego', 'Camila', 'Jorge', 'Valentina', 'Andres', 'Daniela', 'Manuel', 'Carolina', 'Felipe', 'Fernanda', 'Ricardo', 'Sofia'];
$apellidos = ['Silva', 'Gonzalez', 'Rojas', 'Muñoz', 'Diaz', 'Perez', 'Soto', 'Contreras', 'Morales', 'Lopez', 'Rodriguez', 'Martinez', 'Fuentes', 'Valenzuela', 'Araya', 'Sepulveda', 'Espinoza', 'Vargas', 'Castillo', 'Tapia'];

$pdo = getConnection();
$password_hash = '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa'; // Clave: 12345

echo "<h1>🚀 Iniciando Generación Masiva de 100 Usuarios...</h1>";
echo "<table border='1' style='border-collapse:collapse; width:100%; font-family:sans-serif;'>";
echo "<tr style='background:#ddd;'><th>#</th><th>RUT</th><th>Nombre</th><th>Rol</th><th>Email</th><th>Estado</th></tr>";

try {
    $pdo->beginTransaction();

    // Generar 100 usuarios partiendo del RUT 30.000.000 para no chocar
    $inicio_rut = 30000000;

    for ($i = 1; $i <= 100; $i++) {
        $rut_num = $inicio_rut + $i;
        $rut_completo = calcularRut($rut_num);
        
        $nom = $nombres[array_rand($nombres)];
        $ape = $apellidos[array_rand($apellidos)];
        
        // Distribución de Roles: 90% Profesores (2), 5% Admin BD (1), 5% Admin DAEM (3)
        $rand = rand(1, 100);
        if ($rand <= 90) { $rol = 2; $rol_nom = "Profesor"; }
        elseif ($rand <= 95) { $rol = 1; $rol_nom = "Admin BD"; }
        else { $rol = 3; $rol_nom = "Admin DAEM"; }

        $email = strtolower($nom . "." . $ape . "." . $i . "@escuela.cl");
        $telefono = "+569" . rand(10000000, 99999999);

        // Insertar
        $sql = "INSERT INTO Usuario (Id_Rol, Rut, Nombre, Apellido, Contraseña, Telefono, Email, Estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rol, $rut_completo, $nom, $ape, $password_hash, $telefono, $email]);

        echo "<tr>
                <td>$i</td>
                <td>$rut_completo</td>
                <td>$nom $ape</td>
                <td>$rol_nom</td>
                <td>$email</td>
                <td style='color:green'>Creado</td>
              </tr>";
    }

    $pdo->commit();
    echo "</table>";
    echo "<h2>✅ ¡Proceso Terminado Exitosamente!</h2>";
    echo "<p><a href='login.php'>Ir al Login</a></p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "</table>";
    echo "<h2 style='color:red'>❌ Error Fatal: " . $e->getMessage() . "</h2>";
}
?>