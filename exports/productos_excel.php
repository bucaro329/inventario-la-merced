<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
requireLogin();

$conn = getConnection();

// Obtener productos
$productos = $conn->query("
    SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    ORDER BY p.nombre
");

// Configurar headers para descarga Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="productos_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1">';
echo '<tr style="background-color: #960f1c; color: white; font-weight: bold;">';
echo '<th>Código</th>';
echo '<th>Nombre</th>';
echo '<th>Descripción</th>';
echo '<th>Categoría</th>';
echo '<th>Precio</th>';
echo '<th>Stock</th>';
echo '<th>Unidad de Medida</th>';
echo '<th>Estado</th>';
echo '</tr>';

while ($producto = $productos->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($producto['codigo']) . '</td>';
    echo '<td>' . htmlspecialchars($producto['nombre']) . '</td>';
    echo '<td>' . htmlspecialchars($producto['descripcion'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría') . '</td>';
    echo '<td>' . number_format($producto['precio'], 2) . '</td>';
    echo '<td>' . $producto['stock'] . '</td>';
    echo '<td>' . htmlspecialchars($producto['unidad_medida']) . '</td>';
    echo '<td>' . ucfirst($producto['estado']) . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</body></html>';

closeConnection($conn);
exit;
?>

