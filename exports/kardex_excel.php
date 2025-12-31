<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
requireLogin();

$conn = getConnection();

// Obtener parámetros de filtro
$producto_id = $_GET['producto_id'] ?? '';
$tipo_movimiento = $_GET['tipo_movimiento'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Construir consulta
$where = [];

if ($producto_id) {
    $where[] = "m.producto_id = " . intval($producto_id);
}

if ($tipo_movimiento) {
    $where[] = "m.tipo_movimiento = '" . $conn->real_escape_string($tipo_movimiento) . "'";
}

$where[] = "DATE(m.fecha_movimiento) BETWEEN '" . $conn->real_escape_string($fecha_desde) . "' AND '" . $conn->real_escape_string($fecha_hasta) . "'";

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Obtener movimientos
$movimientos = $conn->query("
    SELECT m.*, 
           p.codigo as producto_codigo,
           p.nombre as producto_nombre,
           u.nombre_completo as usuario_nombre
    FROM movimientos m
    INNER JOIN productos p ON m.producto_id = p.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    $where_clause
    ORDER BY m.fecha_movimiento DESC
");

// Configurar headers para descarga Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="kardex_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<h2>Kardex / Movimientos de Inventario</h2>';
echo '<p>Período: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)) . '</p>';
echo '<table border="1">';
echo '<tr style="background-color: #960f1c; color: white; font-weight: bold;">';
echo '<th>Fecha/Hora</th>';
echo '<th>Código Producto</th>';
echo '<th>Producto</th>';
echo '<th>Tipo</th>';
echo '<th>Cantidad</th>';
echo '<th>Stock Antes</th>';
echo '<th>Stock Después</th>';
echo '<th>Usuario</th>';
echo '<th>Referencia</th>';
echo '<th>Observaciones</th>';
echo '</tr>';

while ($mov = $movimientos->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . date('d/m/Y H:i:s', strtotime($mov['fecha_movimiento'])) . '</td>';
    echo '<td>' . htmlspecialchars($mov['producto_codigo']) . '</td>';
    echo '<td>' . htmlspecialchars($mov['producto_nombre']) . '</td>';
    echo '<td>' . strtoupper($mov['tipo_movimiento']) . '</td>';
    echo '<td>' . $mov['cantidad'] . '</td>';
    echo '<td>' . $mov['stock_antes'] . '</td>';
    echo '<td>' . $mov['stock_despues'] . '</td>';
    echo '<td>' . htmlspecialchars($mov['usuario_nombre'] ?? 'N/A') . '</td>';
    echo '<td>' . strtoupper($mov['referencia_tipo']) . ' #' . $mov['referencia_id'] . '</td>';
    echo '<td>' . htmlspecialchars($mov['observaciones'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</body></html>';

closeConnection($conn);
exit;
?>

