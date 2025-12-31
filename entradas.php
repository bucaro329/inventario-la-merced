<?php
$page_title = 'Entradas de Inventario - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();
$current_user = getCurrentUser();
$mensaje = '';
$tipo_mensaje = '';

// Procesar entrada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_entrada') {
    $productos = json_decode($_POST['productos'], true);
    
    if (empty($productos)) {
        $mensaje = "Debe agregar al menos un producto";
        $tipo_mensaje = "error";
    } else {
        $conn->begin_transaction();
        
        try {
            // Generar número de entrada
            $numero_entrada = 'ENT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insertar entrada
            $stmt = $conn->prepare("INSERT INTO entradas (numero_entrada, fecha_entrada, observaciones, usuario_id) VALUES (?, NOW(), ?, ?)");
            $observaciones = trim($_POST['observaciones'] ?? '');
            $usuario_id = $current_user['id'];
            $stmt->bind_param("ssi", $numero_entrada, $observaciones, $usuario_id);
            $stmt->execute();
            $entrada_id = $conn->insert_id;
            
            // Insertar detalles y actualizar stock
            foreach ($productos as $p) {
                // Insertar detalle
                $stmt = $conn->prepare("INSERT INTO detalle_entradas (entrada_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $entrada_id, $p['id'], $p['cantidad'], $p['precio']);
                $stmt->execute();
                
                // Obtener stock actual
                $result = $conn->query("SELECT stock FROM productos WHERE id = " . intval($p['id']));
                $stock_antes = $result->fetch_assoc()['stock'];
                $stock_despues = $stock_antes + $p['cantidad'];
                
                // Actualizar stock
                $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                $stmt->bind_param("ii", $p['cantidad'], $p['id']);
                $stmt->execute();
                
                // Registrar movimiento
                $stmt = $conn->prepare("INSERT INTO movimientos (producto_id, tipo_movimiento, referencia_id, referencia_tipo, cantidad, stock_antes, stock_despues, fecha_movimiento, usuario_id, observaciones) VALUES (?, 'entrada', ?, 'entrada', ?, ?, ?, NOW(), ?, ?)");
                $obs_mov = $observaciones ?: '';
                $stmt->bind_param("iiiiiis", $p['id'], $entrada_id, $p['cantidad'], $stock_antes, $stock_despues, $usuario_id, $obs_mov);
                $stmt->execute();
            }
            
            $conn->commit();
            header("Location: entradas.php?success=1&id=" . $entrada_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al procesar la entrada: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

if (isset($_GET['success'])) {
    $mensaje = "Entrada registrada exitosamente";
    $tipo_mensaje = "success";
}

// Obtener productos activos
$productos_disponibles = $conn->query("SELECT * FROM productos WHERE estado = 'activo' ORDER BY nombre");

// Obtener entradas recientes
$entradas = $conn->query("
    SELECT e.*, u.nombre_completo as usuario_nombre,
           COUNT(d.id) as total_productos
    FROM entradas e
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    LEFT JOIN detalle_entradas d ON e.id = d.entrada_id
    GROUP BY e.id
    ORDER BY e.fecha_entrada DESC
    LIMIT 50
");
require_once 'includes/header.php';
?>
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h2>Entradas de Inventario</h2>
            </div>

            <form method="POST" id="entradaForm" action="">
                <input type="hidden" name="action" value="create_entrada">
                <input type="hidden" name="productos" id="productosJson">
                
                <div class="form-container">
                    <h3>📥 Nueva Entrada de Inventario</h3>
                    
                    <div class="form-group">
                        <label for="observaciones">📝 Observaciones (Opcional)</label>
                        <textarea id="observaciones" name="observaciones" rows="2" placeholder="Ej: Compra a proveedor, devolución de cliente, etc..."></textarea>
                    </div>

                    <div class="add-product-section">
                        <h4>➕ Agregar Producto a la Entrada</h4>
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label for="producto_select">🔍 Buscar Producto</label>
                                <select id="producto_select" class="form-control">
                                    <option value="">-- Seleccione un producto --</option>
                                    <?php while ($prod = $productos_disponibles->fetch_assoc()): ?>
                                        <option value="<?php echo $prod['id']; ?>" 
                                                data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                                data-precio="<?php echo $prod['precio']; ?>"
                                                data-codigo="<?php echo htmlspecialchars($prod['codigo']); ?>"
                                                data-stock="<?php echo $prod['stock']; ?>">
                                            <?php echo htmlspecialchars($prod['codigo'] . ' - ' . $prod['nombre'] . ' (Stock actual: ' . $prod['stock'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cantidad">📦 Cantidad</label>
                                <input type="number" id="cantidad" min="1" value="1" class="form-control" placeholder="Ej: 10">
                            </div>
                            
                            <div class="form-group">
                                <label for="precio_entrada">💰 Precio Unitario</label>
                                <input type="number" id="precio_entrada" step="0.01" min="0" value="0" class="form-control" placeholder="0.00">
                                <small style="color: var(--text-secondary); font-size: 0.85em;">Dejar en 0 para usar precio del producto</small>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="button" class="btn btn-primary" onclick="agregarProducto()" style="width: 100%; height: 42px;">
                                    ➕ Agregar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="productos-lista" id="productosLista" style="display: none;">
                        <h4>📋 Productos Agregados (<span id="contadorProductos">0</span>)</h4>
                        <div style="overflow-x: auto;">
                            <table class="data-table" id="tablaProductos">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unit.</th>
                                        <th>Subtotal</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaProductosBody">
                                    <tr id="emptyRow">
                                        <td colspan="6" class="text-center">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">📦</div>
                                                <p>No hay productos agregados aún</p>
                                                <small>Selecciona un producto arriba y haz clic en "Agregar"</small>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot id="tablaFooter" style="display: none;">
                                    <tr style="background: var(--light-bg); font-weight: 700;">
                                        <td colspan="4" class="text-right">TOTAL:</td>
                                        <td id="totalEntrada">Q0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border-color);">
                        <button type="submit" class="btn btn-success" id="btnProcesar" disabled style="font-size: 1.1em; padding: 14px 30px;">
                            ✅ Registrar Entrada
                        </button>
                        <a href="index.php" class="btn btn-secondary">❌ Cancelar</a>
                    </div>
                </div>
            </form>

            <!-- Historial de entradas -->
            <div class="table-container">
                <h3>Historial de Entradas</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Productos</th>
                            <th>Usuario</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($entradas->num_rows > 0): ?>
                            <?php while ($entrada = $entradas->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entrada['numero_entrada']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($entrada['fecha_entrada'])); ?></td>
                                    <td><?php echo $entrada['total_productos']; ?></td>
                                    <td><?php echo htmlspecialchars($entrada['usuario_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($entrada['observaciones'] ?? ''); ?></td>
                                    <td class="actions">
                                        <a href="detalle_entrada.php?id=<?php echo $entrada['id']; ?>" class="btn-icon" title="Ver Detalle">👁️</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay entradas registradas</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
<script>
    let productosEntrada = [];
    
    function agregarProducto() {
        const select = document.getElementById('producto_select');
        const cantidad = parseInt(document.getElementById('cantidad').value);
        const precio = parseFloat(document.getElementById('precio_entrada').value);
        const option = select.options[select.selectedIndex];
        
        if (!select.value || cantidad < 1) {
            alert('⚠️ Por favor seleccione un producto y una cantidad válida');
            return;
        }
        
        const productoId = parseInt(select.value);
        const precioFinal = precio > 0 ? precio : parseFloat(option.dataset.precio);
        
        // Verificar si ya existe
        const existe = productosEntrada.findIndex(p => p.id === productoId);
        if (existe >= 0) {
            productosEntrada[existe].cantidad += cantidad;
            productosEntrada[existe].precio = precioFinal;
            productosEntrada[existe].subtotal = productosEntrada[existe].cantidad * precioFinal;
        } else {
            productosEntrada.push({
                id: productoId,
                codigo: option.dataset.codigo,
                nombre: option.dataset.nombre,
                cantidad: cantidad,
                precio: precioFinal,
                subtotal: cantidad * precioFinal
            });
        }
        
        actualizarTabla();
        select.value = '';
        document.getElementById('cantidad').value = 1;
        document.getElementById('precio_entrada').value = 0;
        select.focus();
    }
    
    function eliminarProducto(index) {
        if (confirm('¿Está seguro de eliminar este producto de la lista?')) {
            productosEntrada.splice(index, 1);
            actualizarTabla();
        }
    }
    
    function actualizarTabla() {
        const tbody = document.getElementById('tablaProductosBody');
        const emptyRow = document.getElementById('emptyRow');
        const btnProcesar = document.getElementById('btnProcesar');
        const productosLista = document.getElementById('productosLista');
        const tablaFooter = document.getElementById('tablaFooter');
        const contador = document.getElementById('contadorProductos');
        
        if (productosEntrada.length === 0) {
            tbody.innerHTML = `
                <tr id="emptyRow">
                    <td colspan="6" class="text-center">
                        <div class="empty-state">
                            <div class="empty-state-icon">📦</div>
                            <p>No hay productos agregados aún</p>
                            <small>Selecciona un producto arriba y haz clic en "Agregar"</small>
                        </div>
                    </td>
                </tr>
            `;
            btnProcesar.disabled = true;
            document.getElementById('productosJson').value = '';
            productosLista.style.display = 'none';
            tablaFooter.style.display = 'none';
            contador.textContent = '0';
            return;
        }
        
        productosLista.style.display = 'block';
        tablaFooter.style.display = '';
        emptyRow.remove();
        btnProcesar.disabled = false;
        contador.textContent = productosEntrada.length;
        
        let html = '';
        let total = 0;
        
        productosEntrada.forEach((p, index) => {
            p.subtotal = p.cantidad * p.precio;
            total += p.subtotal;
            html += `
                <tr style="animation: fadeIn 0.3s ease;">
                    <td><strong>${p.codigo}</strong></td>
                    <td>${p.nombre}</td>
                    <td><span class="stock-badge success">${p.cantidad}</span></td>
                    <td>Q${p.precio.toFixed(2)}</td>
                    <td><strong>Q${p.subtotal.toFixed(2)}</strong></td>
                    <td>
                        <button type="button" class="btn-icon" onclick="eliminarProducto(${index})" title="Eliminar" style="color: var(--danger-color);">
                            🗑️
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        document.getElementById('totalEntrada').textContent = 'Q' + total.toFixed(2);
        document.getElementById('productosJson').value = JSON.stringify(productosEntrada);
    }
    
    // Permitir agregar con Enter
    document.getElementById('cantidad').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            agregarProducto();
        }
    });
    
    document.getElementById('precio_entrada').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            agregarProducto();
        }
    });
</script>
<?php closeConnection($conn); ?>

