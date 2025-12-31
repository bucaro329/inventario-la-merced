<?php
session_start();
require_once 'config/database.php';

$conn = getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de recibo no válido");
}

$salida_id = intval($_GET['id']);

// Obtener información de la salida
$result = $conn->query("SELECT * FROM salidas WHERE id = $salida_id");
if ($result->num_rows == 0) {
    die("Recibo no encontrado");
}
$salida = $result->fetch_assoc();

// Obtener detalles
$detalles = $conn->query("
    SELECT d.*, p.codigo, p.nombre, p.unidad_medida 
    FROM detalle_salidas d 
    INNER JOIN productos p ON d.producto_id = p.id 
    WHERE d.salida_id = $salida_id
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo #<?php echo htmlspecialchars($salida['numero_recibo']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
            .recibo-container { box-shadow: none; border: none; }
        }
        .recibo-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .recibo-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .recibo-info {
            margin-bottom: 20px;
        }
        .recibo-info p {
            margin: 5px 0;
        }
        .recibo-table {
            width: 100%;
            margin: 20px 0;
        }
        .recibo-total {
            text-align: right;
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="recibo-container">
            <div class="recibo-header">
                <h1>LA MERCED</h1>
                <h2>Sistema de Inventario</h2>
                <p>RECIBO DE SALIDA</p>
            </div>
            
            <div class="recibo-info">
                <p><strong>Número de Recibo:</strong> <?php echo htmlspecialchars($salida['numero_recibo']); ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s', strtotime($salida['fecha_salida'])); ?></p>
                <?php if ($salida['usuario']): ?>
                    <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($salida['usuario']); ?></p>
                <?php endif; ?>
            </div>
            
            <table class="recibo-table data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($detalle = $detalles->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detalle['codigo']); ?></td>
                            <td><?php echo htmlspecialchars($detalle['nombre']); ?></td>
                            <td><?php echo $detalle['cantidad'] . ' ' . htmlspecialchars($detalle['unidad_medida']); ?></td>
                            <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                            <td>$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <div class="recibo-total">
                <p>TOTAL: $<?php echo number_format($salida['total'], 2); ?></p>
            </div>
            
            <?php if ($salida['observaciones']): ?>
                <div class="recibo-info">
                    <p><strong>Observaciones:</strong> <?php echo htmlspecialchars($salida['observaciones']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="text-center no-print" style="margin-top: 30px;">
                <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Recibo</button>
                <a href="salidas.php" class="btn btn-secondary">Nueva Salida</a>
                <a href="reportes.php" class="btn btn-secondary">Ver Reportes</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php closeConnection($conn); ?>

