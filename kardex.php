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
    SELECT m.id,
        m.fecha_movimiento,
        p.codigo as producto_codigo,
        p.nombre as producto_nombre,
        m.tipo_movimiento,
        m.cantidad,
        p.precio as precio_unitario,
        (m.cantidad * p.precio) AS total_linea,
        m.stock_despues as stock_actual,
        u.nombre_completo as usuario_nombre,
        m.referencia_id,
        m.referencia_tipo,
        m.observaciones
    FROM movimientos m
    INNER JOIN productos p ON p.id = m.producto_id
    LEFT JOIN usuarios u ON u.id = m.usuario_id
    $where_clause
    ORDER BY m.fecha_movimiento DESC, m.referencia_tipo DESC, m.referencia_id DESC, m.id DESC
    LIMIT 500;
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$movimientos = $stmt->get_result();

// Calcular totales por salida y totales generales
$total_cantidad_general = 0;
$total_venta_general = 0;
$totales_por_salida = [];
$movimientos_array = [];

while ($mov = $movimientos->fetch_assoc()) {
    $movimientos_array[] = $mov;
    $total_cantidad_general += $mov['cantidad'];
    
    // Solo calcular totales para salidas
    if ($mov['tipo_movimiento'] == 'salida') {
        $total_venta_general += $mov['total_linea'];
        
        $ref_key = $mov['referencia_tipo'] . '_' . $mov['referencia_id'];
        if (!isset($totales_por_salida[$ref_key])) {
            $totales_por_salida[$ref_key] = [
                'referencia' => strtoupper($mov['referencia_tipo']) . ' #' . $mov['referencia_id'],
                'total_cantidad' => 0,
                'total_venta' => 0
            ];
        }
        $totales_por_salida[$ref_key]['total_cantidad'] += $mov['cantidad'];
        $totales_por_salida[$ref_key]['total_venta'] += $mov['total_linea'];
    }
}

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
            <div class="table-container" id="kardex-imprimible">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>Total</th>
                            <th>Stock Actual</th>
                            <th>Usuario</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($movimientos_array)): ?>
                            <?php 
                            $salida_anterior = null;
                            foreach ($movimientos_array as $index => $mov): 
                                $ref_key = $mov['referencia_tipo'] . '_' . $mov['referencia_id'];
                            ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($mov['producto_codigo']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($mov['producto_nombre']); ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo $mov['tipo_movimiento'] == 'entrada' ? 'success' : 'warning'; ?>">
                                            <?php echo strtoupper($mov['tipo_movimiento']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $mov['cantidad']; ?></td>
                                    <td><?php echo $mov['tipo_movimiento'] == 'salida' ? 'Q' . number_format($mov['precio_unitario'], 2) : '-'; ?></td>
                                    <td><?php echo $mov['tipo_movimiento'] == 'salida' ? 'Q' . number_format($mov['total_linea'], 2) : '-'; ?></td>
                                    <td><strong><?php echo $mov['stock_actual']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $ref = strtoupper($mov['referencia_tipo']);
                                        echo $ref . ' #' . $mov['referencia_id'];
                                        ?>
                                    </td>
                                </tr>
                                
                                <?php 
                                // Verificar si es el último movimiento de esta salida para mostrar el total
                                $es_ultimo = ($index == count($movimientos_array) - 1);
                                $es_siguiente_diferente = false;
                                
                                if (!$es_ultimo) {
                                    $siguiente = $movimientos_array[$index + 1];
                                    $siguiente_ref = $siguiente['referencia_tipo'] . '_' . $siguiente['referencia_id'];
                                    // Si el siguiente movimiento es de una salida diferente o no es salida
                                    $es_siguiente_diferente = ($mov['tipo_movimiento'] == 'salida' && 
                                                               ($ref_key != $siguiente_ref || $siguiente['tipo_movimiento'] != 'salida'));
                                }
                                
                                // Mostrar total después del último producto de la salida
                                if ($mov['tipo_movimiento'] == 'salida' && isset($totales_por_salida[$ref_key]) && 
                                    ($es_ultimo || $es_siguiente_diferente)): ?>
                                    <tr style="background: #f0f0f0; font-weight: bold;">
                                        <td colspan="4" class="text-right">TOTAL <?php echo $totales_por_salida[$ref_key]['referencia']; ?>:</td>
                                        <td><strong><?php echo $totales_por_salida[$ref_key]['total_cantidad']; ?></strong></td>
                                        <td></td>
                                        <td><strong>Q<?php echo number_format($totales_por_salida[$ref_key]['total_venta'], 2); ?></strong></td>
                                        <td colspan="3"></td>
                                    </tr>
                                <?php endif;
                                
                                $salida_anterior = $ref_key;
                            endforeach; ?>
                            <!-- Totales generales -->
                            <tr style="background: #960f1c; color: white; font-weight: bold; font-size: 1.1em;">
                                <td colspan="4" class="text-right"><strong>TOTAL GENERAL:</strong></td>
                                <td><strong><?php echo $total_cantidad_general; ?></strong></td>
                                <td></td>
                                <td><strong>Q<?php echo number_format($total_venta_general, 2); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">No hay movimientos en el período seleccionado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Botón de impresión -->
            <div class="form-actions">
                <button onclick="imprimirKardex()" class="btn btn-primary">🖨️ Imprimir / Guardar como PDF</button>
            </div>
<?php require_once 'includes/footer.php'; ?>
<script>
function imprimirKardex() {
    const contenido = document.getElementById('kardex-imprimible').innerHTML;
    const fecha_desde = '<?php echo date('d/m/Y', strtotime($fecha_desde)); ?>';
    const fecha_hasta = '<?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>';
    const fecha_export = new Date().toLocaleString('es-GT');
    
    const ventanaImpresion = window.open('', '_blank');
    ventanaImpresion.document.write(`
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Kardex - La Merced</title>
            <style>
                @page {
                    margin: 1.5cm;
                    size: A4 landscape;
                }
                
                body {
                    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    font-size: 10px;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                
                .header-print {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #960f1c;
                    padding-bottom: 15px;
                }
                
                .header-print h1 {
                    color: #960f1c;
                    margin: 0 0 10px 0;
                    font-size: 20px;
                }
                
                .header-print p {
                    margin: 5px 0;
                    color: #666;
                    font-size: 12px;
                }
                
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    font-size: 9px;
                }
                
                .data-table th {
                    background: #960f1c;
                    color: white;
                    padding: 8px 6px;
                    text-align: left;
                    font-weight: 600;
                    border: 1px solid #7a0c16;
                }
                
                .data-table td {
                    padding: 6px;
                    border: 1px solid #ddd;
                    border-top: none;
                }
                
                .data-table tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }
                
                .data-table tbody tr[style*="background: #f0f0f0"] {
                    background: #e9ecef !important;
                    font-weight: bold;
                }
                
                .data-table tbody tr[style*="background: #960f1c"] {
                    background: #960f1c !important;
                    color: white !important;
                }
                
                .text-right {
                    text-align: right;
                }
                
                .stock-badge {
                    padding: 3px 6px;
                    border-radius: 3px;
                    font-weight: 600;
                    font-size: 8px;
                }
                
                .stock-badge.success {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .stock-badge.warning {
                    background: #fef3c7;
                    color: #92400e;
                }
                
                @media print {
                    body {
                        padding: 0;
                    }
                    
                    .data-table {
                        font-size: 8px;
                    }
                    
                    .data-table th,
                    .data-table td {
                        padding: 4px 3px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header-print">
                <h1>📋 Kardex / Movimientos de Inventario</h1>
                <p><strong>La Merced - Sistema de Inventario</strong></p>
                <p>Período: ${fecha_desde} - ${fecha_hasta}</p>
                <p>Fecha de exportación: ${fecha_export}</p>
            </div>
            ${contenido}
        </body>
        </html>
    `);
    
    ventanaImpresion.document.close();
    
    setTimeout(() => {
        ventanaImpresion.focus();
        ventanaImpresion.print();
    }, 250);
}
</script>
<?php closeConnection($conn); ?>

