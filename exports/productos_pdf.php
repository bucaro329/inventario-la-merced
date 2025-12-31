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

// Generar HTML para PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Productos - La Merced</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        h1 {
            color: #960f1c;
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #960f1c;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 6px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .header-info {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>📦 Listado de Productos</h1>
    <div class="header-info">
        <p><strong>La Merced - Sistema de Inventario</strong></p>
        <p>Fecha de exportación: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Unidad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($producto = $productos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                    <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                    <td><?php echo $producto['stock']; ?></td>
                    <td><?php echo htmlspecialchars($producto['unidad_medida']); ?></td>
                    <td><?php echo ucfirst($producto['estado']); ?></td>
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

// Generar HTML que el navegador puede convertir a PDF usando la función de impresión
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Productos - La Merced</title>
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
            font-size: 10px;
            padding: 20px;
        }
        h1 {
            color: #960f1c;
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #960f1c;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 6px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .header-info {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
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

