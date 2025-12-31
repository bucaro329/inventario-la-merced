<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventario_la_merced');

// Función para conectar a la base de datos
function getConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Error al conectar con la base de datos: " . $e->getMessage());
    }
}

// Función para cerrar la conexión
function closeConnection($conn) {
    if ($conn && $conn instanceof mysqli) {
        // Verificar si la conexión está abierta antes de cerrarla
        if (mysqli_ping($conn)) {
            $conn->close();
        }
    }
}
?>

