<?php
function getConnection() {
    
    // --- DATOS DE XAMPP (MySQL) ---
    $host = 'localhost';
    $port = '3306'; // Puerto por defecto de MySQL
    $db   = 'nutridata'; // <-- ¡Asegúrate de crear esta BD en phpMyAdmin!
    $user = 'root';  // <-- Usuario por defecto de XAMPP
    $pass = ''; // <-- Contraseña por defecto de XAMPP (vacía)
    // ---------------------------------

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        // Intentamos crear la conexión
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;

    } catch (PDOException $e) {
        echo "¡ERROR DE CONEXIÓN! \n";
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}
?>