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

// Generar HTML para PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kardex - La Merced</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
        }
        h1 {
            color: #960f1c;
            text-align: center;
            margin-bottom: 10px;
        }
        .header-info {
            text-align: center;
            margin-bottom: 15px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #960f1c;
            color: white;
            padding: 6px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
        }
        td {
            border: 1px solid #ddd;
            padding: 4px;
            font-size: 8px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>📋 Kardex / Movimientos de Inventario</h1>
    <div class="header-info">
        <p><strong>La Merced - Sistema de Inventario</strong></p>
        <p>Período: <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?></p>
        <p>Fecha de exportación: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Código</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Cantidad</th>
                <th>Stock Antes</th>
                <th>Stock Después</th>
                <th>Usuario</th>
                <th>Referencia</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($mov = $movimientos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                    <td><?php echo htmlspecialchars($mov['producto_codigo']); ?></td>
                    <td><?php echo htmlspecialchars($mov['producto_nombre']); ?></td>
                    <td><?php echo strtoupper($mov['tipo_movimiento']); ?></td>
                    <td><?php echo $mov['cantidad']; ?></td>
                    <td><?php echo $mov['stock_antes']; ?></td>
                    <td><?php echo $mov['stock_despues']; ?></td>
                    <td><?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'N/A'); ?></td>
                    <td><?php echo strtoupper($mov['referencia_tipo']) . ' #' . $mov['referencia_id']; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

// Cerrar conexión antes de enviar headers
closeConnection($conn);

// Configurar headers
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex - La Merced</title>
    <link rel="icon" href="img/logo_menu.png" type="image/png">
    <style>
        @media print {
            @page {
                margin: 1cm;
                size: A4 landscape;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            padding: 20px;
        }
        h1 {
            color: #960f1c;
            text-align: center;
            margin-bottom: 10px;
        }
        .header-info {
            text-align: center;
            margin-bottom: 15px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #960f1c;
            color: white;
            padding: 6px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
        }
        td {
            border: 1px solid #ddd;
            padding: 4px;
            font-size: 8px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .print-button {
            text-align: center;
            margin: 20px 0;
        }
        .btn-print {
            background: #960f1c;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .btn-print:hover {
            background: #7a0c16;
        }
    </style>
</head>
<body>
    <div class="print-button no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
        <p style="margin-top: 10px; color: #666;">Usa Ctrl+P o el botón para imprimir y guardar como PDF</p>
    </div>
    
    <?php echo $html; ?>
    
    <script>
        // Auto-imprimir si se accede directamente
        if (window.location.search.indexOf('auto=1') !== -1) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>
<?php exit; ?>

