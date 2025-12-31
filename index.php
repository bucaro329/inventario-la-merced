<?php
$page_title = 'Sistema de Inventario - Souvenir La Merced';
require_once 'includes/header.php';
?>
            <div class="dashboard">
                <div class="welcome-section">
                <h2>Bienvenido al Sistema de Inventario</h2>
                <p>Gestiona tu inventario de manera eficiente</p>
            </div>

            <div class="stats-grid">
                <?php
                $conn = getConnection();
                
                // Total de productos activos
                $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE estado = 'activo'");
                $total_productos = $result->fetch_assoc()['total'];
                
                // Total de entradas hoy
                $result = $conn->query("SELECT COUNT(*) as total FROM entradas WHERE DATE(fecha_entrada) = CURDATE()");
                $entradas_hoy = $result->fetch_assoc()['total'];
                
                // Total de salidas hoy
                $result = $conn->query("SELECT COUNT(*) as total FROM salidas WHERE DATE(fecha_salida) = CURDATE()");
                $salidas_hoy = $result->fetch_assoc()['total'];
                
                // Productos con stock bajo (menos de 10)
                $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock < 10 AND estado = 'activo'");
                $stock_bajo = $result->fetch_assoc()['total'];
                
                closeConnection($conn);
                ?>
                
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $total_productos; ?></h3>
                        <p>Productos Activos</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📥</div>
                    <div class="stat-info">
                        <h3><?php echo $entradas_hoy; ?></h3>
                        <p>Ingresos Hoy</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📤</div>
                    <div class="stat-info">
                        <h3><?php echo $salidas_hoy; ?></h3>
                        <p>Ventas Hoy</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-info">
                        <h3><?php echo $stock_bajo; ?></h3>
                        <p>Productos con Stock Bajo</p>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <h3>Acciones Rápidas</h3>
                <div class="actions-grid">
                    <a href="productos.php?action=add" class="action-btn">
                        <span class="action-icon">➕</span>
                        <span>Agregar Producto</span>
                    </a>
                    <a href="entradas.php" class="action-btn">
                        <span class="action-icon">📥</span>
                        <span>Nuevo Ingreso</span>
                    </a>
                    <a href="salidas.php" class="action-btn">
                        <span class="action-icon">📤</span>
                        <span>Nueva Venta</span>
                    </a>
                    <a href="kardex.php" class="action-btn">
                        <span class="action-icon">📋</span>
                        <span>Ver Movimientos</span>
                    </a>
                    <a href="reportes.php" class="action-btn">
                        <span class="action-icon">📊</span>
                        <span>Ver Reportes de Inventario</span>
                    </a>
                </div>
            </div>
            </div>
<?php require_once 'includes/footer.php'; ?>

