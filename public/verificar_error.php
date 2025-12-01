<?php
// verificar_error.php
require_once __DIR__ . '/../src/config/db.php';
$pdo = getConnection();

// 1. Datos que estás intentando usar
$rut_prueba = '11111111-1'; // OJO: Cambia esto por el RUT que estás usando
$pass_prueba = '12345';

echo "<h2>Diagnóstico de Login</h2>";
echo "Intentando entrar con RUT: <strong>$rut_prueba</strong> y Clave: <strong>$pass_prueba</strong><br><br>";

// 2. Buscar usuario en la BD
$stmt = $pdo->prepare("SELECT * FROM Usuario WHERE Rut = ?");
$stmt->execute([$rut_prueba]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo "<span style='color:red'>❌ Error: El usuario con ese RUT no existe en la Base de Datos.</span>";
    exit;
}

echo "Usuario encontrado: " . $usuario['Nombre'] . " " . $usuario['Apellido'] . "<br>";
echo "Contraseña guardada en la BD: <strong>" . $usuario['Contraseña'] . "</strong><br><br>";

// 3. Verificar qué validación funcionaría
$coincide_texto = ($usuario['Contraseña'] === $pass_prueba);
$coincide_hash = password_verify($pass_prueba, $usuario['Contraseña']);

echo "<h3>Resultados de la prueba:</h3>";

if ($coincide_texto) {
    echo "🔵 <strong>Comparación de Texto Plano:</strong> COINCIDE.<br>";
    echo "👉 <strong>Diagnóstico:</strong> Tu base de datos tiene la clave '12345' sin encriptar.<br>";
    echo "⚠️ <strong>Solución:</strong> Debes ejecutar el UPDATE en la base de datos o deshacer el cambio en validar.php.";
} else {
    echo "⚪ Comparación de Texto Plano: No coincide.<br>";
}

if ($coincide_hash) {
    echo "Ctl🟢 <strong>Comparación Segura (password_verify):</strong> COINCIDE.<br>";
    echo "👉 <strong>Diagnóstico:</strong> La base de datos y la clave están bien.<br>";
    echo "⚠️ <strong>Solución:</strong> Si esto sale verde pero no puedes entrar, revisa que guardaste el archivo <code>validar.php</code>.";
} else {
    echo "🔴 Comparación Segura: No coincide.<br>";
}

if (!$coincide_texto && !$coincide_hash) {
    echo "<br><span style='color:red; font-weight:bold'>❌ CONCLUSIÓN: La contraseña en la BD no es '12345' ni su hash correcto.</span><br>";
    echo "Probablemente el Hash se copió incompleto o mal.";
}
?>