<?php
$page_title = 'Gestión de Productos - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Obtener o crear categoría "souvenirs"
$categoria_souvenirs = $conn->query("SELECT id FROM categorias WHERE nombre = 'souvenirs' AND activo = 1 LIMIT 1");
if ($categoria_souvenirs->num_rows == 0) {
    // Crear categoría souvenirs si no existe
    $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES ('souvenirs', 'Categoría para productos souvenirs')");
    $stmt->execute();
    $stmt->close();
    $categoria_souvenirs = $conn->query("SELECT id FROM categorias WHERE nombre = 'souvenirs' AND activo = 1 LIMIT 1");
}
$souvenirs_id = $categoria_souvenirs->fetch_assoc()['id'];

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $codigo = trim($_POST['codigo']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion'] ?? '');
                $precio = floatval($_POST['precio']);
                
                // Validar que el precio sea mayor a 0
                if ($precio <= 0) {
                    $mensaje = "El precio debe ser mayor a 0";
                    $tipo_mensaje = "error";
                    break;
                }
                
                $stock = intval($_POST['stock']);
                $unidad_medida = trim($_POST['unidad_medida']);
                // Asignar automáticamente la categoría souvenirs
                $categoria_id = $souvenirs_id;
                $estado = trim($_POST['estado']);
                
                $stmt = $conn->prepare("INSERT INTO productos (codigo, nombre, descripcion, precio, stock, unidad_medida, categoria_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssddsis", $codigo, $nombre, $descripcion, $precio, $stock, $unidad_medida, $categoria_id, $estado);
                
                if ($stmt->execute()) {
                    $mensaje = "Producto agregado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al agregar producto: " . $stmt->error;
                    $tipo_mensaje = "error";
                }
                $stmt->close();
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $codigo = trim($_POST['codigo']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion'] ?? '');
                $precio = floatval($_POST['precio']);
                
                // Validar que el precio sea mayor a 0
                if ($precio <= 0) {
                    $mensaje = "El precio debe ser mayor a 0";
                    $tipo_mensaje = "error";
                    break;
                }
                
                $stock = intval($_POST['stock']);
                $unidad_medida = trim($_POST['unidad_medida']);
                // Mantener la categoría souvenirs en edición también
                $categoria_id = $souvenirs_id;
                $estado = trim($_POST['estado']);
                
                $stmt = $conn->prepare("UPDATE productos SET codigo=?, nombre=?, descripcion=?, precio=?, stock=?, unidad_medida=?, categoria_id=?, estado=? WHERE id=?");
                $stmt->bind_param("sssddsisi", $codigo, $nombre, $descripcion, $precio, $stock, $unidad_medida, $categoria_id, $estado, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Producto actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar producto: " . $stmt->error;
                    $tipo_mensaje = "error";
                }
                $stmt->close();
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE productos SET estado='inactivo' WHERE id=?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Producto eliminado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al eliminar producto: " . $stmt->error;
                    $tipo_mensaje = "error";
                }
                $stmt->close();
                break;
        }
    }
}

// Obtener productos (activos e inactivos)
$filtro_estado = $_GET['estado'] ?? 'activo';
$where = "WHERE estado = '$filtro_estado'";
if ($filtro_estado == 'todos') {
    $where = "";
}
$productos = $conn->query("SELECT p.*, c.nombre as categoria_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id $where ORDER BY p.nombre");

// Obtener producto para editar
$producto_editar = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM productos WHERE id = $id");
    $producto_editar = $result->fetch_assoc();
}
require_once 'includes/header.php';
?>
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h2>Gestión de Productos</h2>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <select onchange="window.location='?estado='+this.value" style="padding: 8px; border-radius: 6px; border: 2px solid var(--border-color);">
                        <option value="activo" <?php echo $filtro_estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivo" <?php echo $filtro_estado == 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                        <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                    </select>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                        <button class="btn btn-primary" onclick="toggleForm()"><?php echo $producto_editar ? 'Cancelar Edición' : '➕ Agregar Producto'; ?></button>
                        <a href="imports/productos.php" class="btn btn-warning">📥 Importar</a>
                    <?php endif; ?>
                    <a href="exports/productos_excel.php" class="btn btn-success">📊 Excel</a>
                    <a href="exports/productos_pdf.php" class="btn btn-success">📄 PDF</a>
                </div>
            </div>

            <!-- Formulario de producto -->
            <div class="form-container" id="productForm" style="display: <?php echo $producto_editar ? 'block' : 'none'; ?>;">
                <h3><?php echo $producto_editar ? 'Editar Producto' : 'Nuevo Producto'; ?></h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $producto_editar ? 'edit' : 'add'; ?>">
                    <?php if ($producto_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $producto_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo">Código/SKU *</label>
                            <input type="text" id="codigo" name="codigo" required 
                                   value="<?php echo $producto_editar ? htmlspecialchars($producto_editar['codigo']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" required 
                                   value="<?php echo $producto_editar ? htmlspecialchars($producto_editar['nombre']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3"><?php echo $producto_editar ? htmlspecialchars($producto_editar['descripcion']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio">Precio *</label>
                            <input type="number" id="precio" name="precio" step="0.01" min="0.01" required 
                                   value="<?php echo $producto_editar ? $producto_editar['precio'] : ''; ?>"
                                   placeholder="0.00">
                            <small style="color: var(--text-secondary); font-size: 0.85em;">El precio es obligatorio y debe ser mayor a 0</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock">Stock Actual *</label>
                            <input type="number" id="stock" name="stock" min="0" required 
                                   value="<?php echo $producto_editar ? $producto_editar['stock'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="unidad_medida">Unidad de Medida</label>
                            <select id="unidad_medida" name="unidad_medida">
                                <option value="UNIDAD" <?php echo ($producto_editar && $producto_editar['unidad_medida'] == 'UNIDAD') ? 'selected' : ''; ?>>UNIDAD</option>
                                <option value="KG" <?php echo ($producto_editar && $producto_editar['unidad_medida'] == 'KG') ? 'selected' : ''; ?>>KG</option>
                                <option value="LITRO" <?php echo ($producto_editar && $producto_editar['unidad_medida'] == 'LITRO') ? 'selected' : ''; ?>>LITRO</option>
                                <option value="CAJA" <?php echo ($producto_editar && $producto_editar['unidad_medida'] == 'CAJA') ? 'selected' : ''; ?>>CAJA</option>
                                <option value="METRO" <?php echo ($producto_editar && $producto_editar['unidad_medida'] == 'METRO') ? 'selected' : ''; ?>>METRO</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoria_id">Categoría</label>
                            <input type="text" id="categoria_id" value="Souvenirs" disabled style="background: #f1f5f9; cursor: not-allowed;">
                            <input type="hidden" name="categoria_id" value="<?php echo $souvenirs_id; ?>">
                            <small style="color: var(--text-secondary); font-size: 0.85em;">La categoría está fijada en "Souvenirs"</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <select id="estado" name="estado" required>
                                <option value="activo" <?php echo (!$producto_editar || $producto_editar['estado'] == 'activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo ($producto_editar && $producto_editar['estado'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $producto_editar ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($producto_editar): ?>
                            <a href="productos.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Información de importación -->
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
            <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3b82f6;">
                <strong>💡 Tip:</strong> Puedes importar productos masivamente desde un archivo CSV. 
                <a href="imports/productos.php" style="color: #3b82f6; font-weight: 600;">Haz clic aquí para importar productos</a>
            </div>
            <?php endif; ?>
            <!-- Tabla de productos -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Unidad</th>
                            <th>Estado</th>
                            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                            <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($productos->num_rows > 0): 
                        ?>
                            <?php while ($producto = $productos->fetch_assoc()): ?>
                                <tr class="<?php echo $producto['stock'] < 10 && $producto['estado'] == 'activo' ? 'low-stock' : ''; ?>">
                                    <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                    <td>Q<?php echo number_format($producto['precio'], 2); ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo $producto['stock'] < 10 ? 'warning' : 'success'; ?>">
                                            <?php echo $producto['stock']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto['unidad_medida']); ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo $producto['estado'] == 'activo' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($producto['estado']); ?>
                                        </span>
                                    </td>
                                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                        <td class="actions">
                                            <a href="?edit=<?php echo $producto['id']; ?>" class="btn-icon" title="Editar">✏️</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar este producto?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                                <button type="submit" class="btn-icon" title="Eliminar">🗑️</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No hay productos registrados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
<script>
    function toggleForm() {
        const form = document.getElementById('productForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            window.scrollTo({ top: form.offsetTop, behavior: 'smooth' });
        }
    }
</script>
<?php closeConnection($conn); ?>
