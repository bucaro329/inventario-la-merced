<?php
$page_title = 'Detalle de Salida - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de salida no válido");
}

$salida_id = intval($_GET['id']);

// Obtener información de la salida
$result = $conn->query("
    SELECT s.*, u.nombre_completo as usuario_nombre 
    FROM salidas s 
    LEFT JOIN usuarios u ON s.usuario_id = u.id 
    WHERE s.id = $salida_id
");
if ($result->num_rows == 0) {
    die("Salida no encontrada");
}
$salida = $result->fetch_assoc();

// Obtener detalles
$detalles = $conn->query("
    SELECT d.*, p.codigo, p.nombre, p.unidad_medida 
    FROM detalle_salidas d 
    INNER JOIN productos p ON d.producto_id = p.id 
    WHERE d.salida_id = $salida_id
");

$motivos = [
    'uso_interno' => 'Uso Interno',
    'ajuste' => 'Ajuste de Inventario',
    'danio' => 'Daño',
    'perdida' => 'Pérdida',
    'otro' => 'Otro'
];
require_once 'includes/header.php';
?>
            <div class="page-header">
                <h2>Detalle de Salida</h2>
                <a href="salidas.php" class="btn btn-secondary">Volver</a>
            </div>

            <div class="form-container">
                <h3>Información de la Salida</h3>
                <div class="form-row">
                    <div class="form-group">
                        <strong>Número de Salida:</strong> <?php echo htmlspecialchars($salida['numero_salida']); ?>
                    </div>
                    <div class="form-group">
                        <strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s', strtotime($salida['fecha_salida'])); ?>
                    </div>
                    <div class="form-group">
                        <strong>Motivo:</strong> <?php echo $motivos[$salida['motivo']] ?? $salida['motivo']; ?>
                    </div>
                    <div class="form-group">
                        <strong>Usuario:</strong> <?php echo htmlspecialchars($salida['usuario_nombre']); ?>
                    </div>
                </div>
                <?php if ($salida['observaciones']): ?>
                    <div class="form-group">
                        <strong>Observaciones:</strong> <?php echo htmlspecialchars($salida['observaciones']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <h3>Productos en la Salida</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_cantidad = 0;
                        while ($detalle = $detalles->fetch_assoc()): 
                            $total_cantidad += $detalle['cantidad'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($detalle['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($detalle['nombre']); ?></td>
                                <td><?php echo $detalle['cantidad']; ?></td>
                                <td><?php echo htmlspecialchars($detalle['unidad_medida']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-right"><strong>TOTAL:</strong></td>
                            <td><strong><?php echo $total_cantidad; ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
<?php closeConnection($conn); ?>

