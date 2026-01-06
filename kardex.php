<?php
$page_title = 'Kardex / Movimientos - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();

// Filtros
$producto_id = $_GET['producto_id'] ?? '';
$tipo_movimiento = $_GET['tipo_movimiento'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Construir consulta
$where = [];
$params = [];

if ($producto_id) {
    $where[] = "m.producto_id = ?";
    $params[] = intval($producto_id);
}

if ($tipo_movimiento) {
    $where[] = "m.tipo_movimiento = ?";
    $params[] = $tipo_movimiento;
}

$where[] = "DATE(m.fecha_movimiento) BETWEEN ? AND ?";
$params[] = $fecha_desde;
$params[] = $fecha_hasta;

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Obtener movimientos
$sql = "
    SELECT m.fecha_movimiento,
        p.codigo as producto_codigo,
        p.nombre as producto_nombre,
        m.tipo_movimiento,
        m.cantidad,
        p.precio as precio_unitario,
        SUM(m.cantidad * p.precio) AS total_venta,
        m.stock_antes,
        m.stock_despues,
        u.nombre_completo as usuario_nombre,
        m.referencia_id,
        m.referencia_tipo,
        m.observaciones
    FROM movimientos m
    INNER JOIN productos p ON p.id = m.producto_id
    LEFT JOIN usuarios u ON u.id = m.usuario_id
    $where_clause
    GROUP BY m.fecha_movimiento, p.nombre, m.tipo_movimiento,
    m.cantidad, p.precio, m.stock_antes, m.stock_despues, 
    u.nombre_completo, m.referencia_id, m.referencia_tipo,
    m.observaciones
    ORDER BY m.fecha_movimiento DESC
    LIMIT 500;
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$movimientos = $stmt->get_result();

// Obtener productos para el filtro
$productos = $conn->query("SELECT id, codigo, nombre FROM productos WHERE estado = 'activo' ORDER BY nombre");
require_once 'includes/header.php';
?>
            <div class="page-header">
                <h2>Kardex / Movimientos de Inventario</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <?php
                    $params_export = http_build_query([
                        'producto_id' => $producto_id,
                        'tipo_movimiento' => $tipo_movimiento,
                        'fecha_desde' => $fecha_desde,
                        'fecha_hasta' => $fecha_hasta
                    ]);
                    ?>
                    <a href="exports/kardex_excel.php?<?php echo $params_export; ?>" class="btn btn-success">📊 Exportar Excel</a>
                    <a href="exports/kardex_pdf.php?<?php echo $params_export; ?>" class="btn btn-success">📄 Exportar PDF</a>
                </div>
            </div>

            <!-- Filtros -->
            <div class="form-container">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="producto_id">Producto</label>
                            <select id="producto_id" name="producto_id">
                                <option value="">Todos los productos</option>
                                <?php while ($prod = $productos->fetch_assoc()): ?>
                                    <option value="<?php echo $prod['id']; ?>" 
                                            <?php echo ($producto_id == $prod['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prod['codigo'] . ' - ' . $prod['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_movimiento">Tipo de Movimiento</label>
                            <select id="tipo_movimiento" name="tipo_movimiento">
                                <option value="">Todos</option>
                                <option value="entrada" <?php echo $tipo_movimiento == 'entrada' ? 'selected' : ''; ?>>Entrada</option>
                                <option value="salida" <?php echo $tipo_movimiento == 'salida' ? 'selected' : ''; ?>>Salida</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_desde">Fecha Desde</label>
                            <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_hasta">Fecha Hasta</label>
                            <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <a href="kardex.php" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de movimientos -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Total</th>
                            <th>Stock Antes</th>
                            <th>Stock Después</th>
                            <th>Usuario</th>
                            <th>Referencia</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($movimientos->num_rows > 0): ?>
                            <?php while ($mov = $movimientos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($mov['fecha_movimiento'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mov['producto_codigo']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($mov['producto_nombre']); ?></small>
                                    </td>
                                    <td>
                                        <span class="stock-badge <?php echo $mov['tipo_movimiento'] == 'entrada' ? 'success' : 'warning'; ?>">
                                            <?php echo strtoupper($mov['tipo_movimiento']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $mov['cantidad']; ?></td>
                                    <td><?php echo $mov['precio_unitario']; ?></td>
                                    <td><?php echo $mov['total_venta']; ?></td>
                                    <td><?php echo $mov['stock_antes']; ?></td>
                                    <td><strong><?php echo $mov['stock_despues']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $ref = strtoupper($mov['referencia_tipo']);
                                        echo $ref . ' #' . $mov['referencia_id'];
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($mov['observaciones'] ?? ''); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No hay movimientos en el período seleccionado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
<?php closeConnection($conn); ?>

