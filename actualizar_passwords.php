<?php
/**
 * Script para actualizar las contraseñas de los usuarios por defecto
 * Ejecutar una sola vez después de crear la base de datos
 */

require_once 'config/database.php';

$conn = getConnection();

// Contraseña por defecto
$password_default = 'admin123';
$password_hash = password_hash($password_default, PASSWORD_DEFAULT);

echo "Actualizando contraseñas...\n";
echo "Hash generado: $password_hash\n\n";

// Actualizar contraseña de admin
$stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $password_hash);
if ($stmt->execute()) {
    echo "✓ Contraseña de 'admin' actualizada correctamente\n";
} else {
    echo "✗ Error al actualizar 'admin': " . $stmt->error . "\n";
}
$stmt->close();

// Actualizar contraseña de operador
$stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE username = 'operador'");
$stmt->bind_param("s", $password_hash);
if ($stmt->execute()) {
    echo "✓ Contraseña de 'operador' actualizada correctamente\n";
} else {
    echo "✗ Error al actualizar 'operador': " . $stmt->error . "\n";
}
$stmt->close();

closeConnection($conn);

echo "\n¡Listo! Ahora puedes iniciar sesión con:\n";
echo "Usuario: admin / Contraseña: admin123\n";
echo "Usuario: operador / Contraseña: admin123\n";
?>

