<?php
$page_title = 'Reportes - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();

// Filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'stock';

// Obtener stock actual
$stock_actual = $conn->query("
    SELECT p.*, c.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.estado = 'activo'
    ORDER BY p.stock ASC, p.nombre
");

// Productos con bajo inventario
$stock_bajo = $conn->query("
    SELECT p.*, c.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.estado = 'activo' AND p.stock < 10
    ORDER BY p.stock ASC
");

// Entradas por fecha
$entradas_periodo = $conn->query("
    SELECT e.*, u.nombre_completo as usuario_nombre,
           COUNT(d.id) as total_productos,
           SUM(d.cantidad * d.precio_unitario) as valor_total
    FROM entradas e
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    LEFT JOIN detalle_entradas d ON e.id = d.entrada_id
    WHERE DATE(e.fecha_entrada) BETWEEN '$fecha_desde' AND '$fecha_hasta'
    GROUP BY e.id
    ORDER BY e.fecha_entrada DESC
");

// Salidas por fecha
$salidas_periodo = $conn->query("
    SELECT s.*, u.nombre_completo as usuario_nombre,
           COUNT(d.id) as total_productos
    FROM salidas s
    LEFT JOIN usuarios u ON s.usuario_id = u.id
    LEFT JOIN detalle_salidas d ON s.id = d.salida_id
    WHERE DATE(s.fecha_salida) BETWEEN '$fecha_desde' AND '$fecha_hasta'
    GROUP BY s.id
    ORDER BY s.fecha_salida DESC
");

// Estadísticas generales
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM productos WHERE estado = 'activo') as total_productos,
        (SELECT COUNT(*) FROM productos WHERE estado = 'activo' AND stock < 10) as productos_bajo_stock,
        (SELECT COUNT(*) FROM entradas WHERE DATE(fecha_entrada) BETWEEN '$fecha_desde' AND '$fecha_hasta') as total_entradas,
        (SELECT COUNT(*) FROM salidas WHERE DATE(fecha_salida) BETWEEN '$fecha_desde' AND '$fecha_hasta') as total_salidas
")->fetch_assoc();
require_once 'includes/header.php';
?>
            <div class="page-header">
                <h2>Reportes del Sistema</h2>
            </div>

            <!-- Filtros -->
            <div class="form-container">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_reporte">Tipo de Reporte</label>
                            <select id="tipo_reporte" name="tipo_reporte" onchange="this.form.submit()">
                                <option value="stock" <?php echo $tipo_reporte == 'stock' ? 'selected' : ''; ?>>Stock Actual</option>
                                <option value="bajo_stock" <?php echo $tipo_reporte == 'bajo_stock' ? 'selected' : ''; ?>>Productos con Bajo Stock</option>
                                <option value="entradas" <?php echo $tipo_reporte == 'entradas' ? 'selected' : ''; ?>>Entradas por Fecha</option>
                                <option value="salidas" <?php echo $tipo_reporte == 'salidas' ? 'selected' : ''; ?>>Salidas por Fecha</option>
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
                            <a href="reportes.php" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_productos']; ?></h3>
                        <p>Productos Activos</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['productos_bajo_stock']; ?></h3>
                        <p>Productos con Bajo Stock</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📥</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_entradas']; ?></h3>
                        <p>Entradas en el Período</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📤</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_salidas']; ?></h3>
                        <p>Salidas en el Período</p>
                    </div>
                </div>
            </div>

            <div id="reporte-imprimible">

            <!-- Reporte de Stock Actual -->
            <?php if ($tipo_reporte == 'stock'): ?>
                <div class="table-container">
                    <h3>Stock Actual de Productos</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>Unidad</th>
                                <th>Precio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stock_actual->num_rows > 0): ?>
                                <?php while ($producto = $stock_actual->fetch_assoc()): ?>
                                    <tr class="<?php echo $producto['stock'] < 10 ? 'low-stock' : ''; ?>">
                                        <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                        <td>
                                            <span class="stock-badge <?php echo $producto['stock'] < 10 ? 'warning' : 'success'; ?>">
                                                <?php echo $producto['stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($producto['unidad_medida']); ?></td>
                                        <td>Q<?php echo number_format($producto['precio'], 2); ?></td>
                                        <td>
                                            <span class="stock-badge <?php echo $producto['estado'] == 'activo' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($producto['estado']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay productos registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Reporte de Bajo Stock -->
            <?php if ($tipo_reporte == 'bajo_stock'): ?>
                <div class="table-container">
                    <h3>Productos con Bajo Inventario (Stock < 10)</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock Actual</th>
                                <th>Unidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stock_bajo->num_rows > 0): ?>
                                <?php while ($producto = $stock_bajo->fetch_assoc()): ?>
                                    <tr class="low-stock">
                                        <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                        <td>
                                            <span class="stock-badge warning">
                                                <?php echo $producto['stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($producto['unidad_medida']); ?></td>
                                        <td>
                                            <a href="entradas.php" class="btn btn-primary btn-sm">Registrar Entrada</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hay productos con bajo stock</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Reporte de Entradas -->
            <?php if ($tipo_reporte == 'entradas'): ?>
                <div class="table-container">
                    <h3>Entradas por Fecha (<?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>)</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Productos</th>
                                <th>Valor Total</th>
                                <th>Usuario</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($entradas_periodo->num_rows > 0): ?>
                                <?php while ($entrada = $entradas_periodo->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entrada['numero_entrada']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($entrada['fecha_entrada'])); ?></td>
                                        <td><?php echo $entrada['total_productos']; ?></td>
                                        <td>Q<?php echo number_format($entrada['valor_total'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($entrada['usuario_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($entrada['observaciones'] ?? ''); ?></td>
                                        <td class="actions">
                                            <a href="detalle_entrada.php?id=<?php echo $entrada['id']; ?>" class="btn-icon" title="Ver Detalle">👁️</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay entradas en el período seleccionado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Reporte de Salidas -->
            <?php if ($tipo_reporte == 'salidas'): ?>
                <div class="table-container">
                    <h3>Salidas por Fecha (<?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>)</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Productos</th>
                                <th>Usuario</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($salidas_periodo->num_rows > 0): ?>
                                <?php while ($salida = $salidas_periodo->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($salida['numero_salida']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($salida['fecha_salida'])); ?></td>
                                        <td><?php 
                                            $motivos = [
                                                'uso_interno' => 'Uso Interno',
                                                'ajuste' => 'Ajuste',
                                                'danio' => 'Daño',
                                                'perdida' => 'Pérdida',
                                                'otro' => 'Otro'
                                            ];
                                            echo $motivos[$salida['motivo']] ?? $salida['motivo'];
                                        ?></td>
                                        <td><?php echo $salida['total_productos']; ?></td>
                                        <td><?php echo htmlspecialchars($salida['usuario_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($salida['observaciones'] ?? ''); ?></td>
                                        <td class="actions">
                                            <a href="detalle_salida.php?id=<?php echo $salida['id']; ?>" class="btn-icon" title="Ver Detalle">👁️</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay salidas en el período seleccionado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <!-- Botón de impresión -->
            <div class="form-actions">
                <button onclick="imprimirReporte()" class="btn btn-primary">🖨️ Imprimir Reporte</button>
            </div>
<?php require_once 'includes/footer.php'; ?>
<script>
function imprimirReporte() {
    // Ocultar elementos que no se deben imprimir
    const elementosOcultar = document.querySelectorAll('.form-container, .stats-grid, .page-header, .form-actions, .sidebar, .top-bar');
    elementosOcultar.forEach(el => {
        if (el) el.style.display = 'none';
    });
    
    // Imprimir
    window.print();
    
    // Restaurar elementos después de imprimir
    setTimeout(() => {
        elementosOcultar.forEach(el => {
            if (el) el.style.display = '';
        });
    }, 1000);
}
</script>
<style>
@media print {
    @page {
        margin: 1cm;
    }
    
    body {
        visibility: hidden;
    }

    /* Muestra solo el reporte */
    #reporte-imprimible,
    #reporte-imprimible * {
        visibility: visible !important;
    }

    #reporte-imprimible {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    .data-table {
        font-size: 9px;
        border-collapse: collapse;
        width: 100%;
    }

    .data-table th,
    .data-table td {
        border: 1px solid #000;
        padding: 5px;
    }

    .data-table thead {
        background: #960f1c;
        color: #fff;
    }
}
</style>
<?php closeConnection($conn); ?>
