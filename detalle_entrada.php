<?php
$page_title = 'Detalle de Entrada - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de entrada no válido");
}

$entrada_id = intval($_GET['id']);

// Obtener información de la entrada
$result = $conn->query("
    SELECT e.*, u.nombre_completo as usuario_nombre 
    FROM entradas e 
    LEFT JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.id = $entrada_id
");
if ($result->num_rows == 0) {
    die("Entrada no encontrada");
}
$entrada = $result->fetch_assoc();

// Obtener detalles
$detalles = $conn->query("
    SELECT d.*, p.codigo, p.nombre, p.unidad_medida 
    FROM detalle_entradas d 
    INNER JOIN productos p ON d.producto_id = p.id 
    WHERE d.entrada_id = $entrada_id
");
require_once 'includes/header.php';
?>
            <div class="page-header">
                <h2>Detalle de Entrada</h2>
                <a href="entradas.php" class="btn btn-secondary">Volver</a>
            </div>

            <div class="form-container">
                <h3>Información de la Entrada</h3>
                <div class="form-row">
                    <div class="form-group">
                        <strong>Número de Entrada:</strong> <?php echo htmlspecialchars($entrada['numero_entrada']); ?>
                    </div>
                    <div class="form-group">
                        <strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s', strtotime($entrada['fecha_entrada'])); ?>
                    </div>
                    <div class="form-group">
                        <strong>Usuario:</strong> <?php echo htmlspecialchars($entrada['usuario_nombre']); ?>
                    </div>
                </div>
                <?php if ($entrada['observaciones']): ?>
                    <div class="form-group">
                        <strong>Observaciones:</strong> <?php echo htmlspecialchars($entrada['observaciones']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <h3>Productos en la Entrada</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total = 0;
                        while ($detalle = $detalles->fetch_assoc()): 
                            $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                            $total += $subtotal;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($detalle['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($detalle['nombre']); ?></td>
                                <td><?php echo $detalle['cantidad'] . ' ' . htmlspecialchars($detalle['unidad_medida']); ?></td>
                                <td>Q<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                <td>Q<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>TOTAL:</strong></td>
                            <td><strong>Q<?php echo number_format($total, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
<?php closeConnection($conn); ?>

