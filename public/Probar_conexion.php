<?php
echo "--- Iniciando prueba de conexión --- <br>";

// 1. Incluimos el archivo de conexión
require_once __DIR__ . '/../src/config/db.php';

try {
    // 2. Intentamos llamar a la función
    $pdo = getConnection();

    // 3. Si la línea anterior no dio error, ¡conectamos!
    echo "✅ ¡Conexión Exitosa a MySQL (XAMPP)!<br>";
    echo "¡Listo para trabajar! <br>";

} catch (PDOException $e) {
    // 4. Si getConnection() falla, el 'throw' es atrapado aquí
    echo "❌ ¡Falló la conexión!<br>";
    echo "Mensaje: " . $e->getMessage() . "<br>";
}

echo "--- Prueba finalizada --- <br>";
?>