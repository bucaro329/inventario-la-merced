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
                // Obtener precio del producto
                $result_precio = $conn->query("SELECT precio FROM productos WHERE id = " . intval($p['id']));
                $precio_producto = $result_precio->fetch_assoc()['precio'];
                
                // Insertar detalle
                $stmt = $conn->prepare("INSERT INTO detalle_entradas (entrada_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $entrada_id, $p['id'], $p['cantidad'], $precio_producto);
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
    $mensaje = "✅ Entrada registrada exitosamente";
    $tipo_mensaje = "success";
}

// Obtener productos activos
$productos_disponibles = $conn->query("SELECT * FROM productos WHERE estado = 'activo' ORDER BY nombre");

// Obtener entradas recientes
$entradas = $conn->query("
    SELECT e.*, u.nombre_completo as usuario_nombre,
           COUNT(d.id) as total_productos,
           SUM(d.cantidad * d.precio_unitario) as total_valor
    FROM entradas e
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    LEFT JOIN detalle_entradas d ON e.id = d.entrada_id
    GROUP BY e.id
    ORDER BY e.fecha_entrada DESC
    LIMIT 50
");
require_once 'includes/header.php';
?>

<style>
/* Estilos mejorados para una interfaz más intuitiva */
.wizard-container {
    background: linear-gradient(135deg, #cebc40 0%, #e3c70c 100%);
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.wizard-header {
    text-align: center;
    color: white;
    margin-bottom: 30px;
}

.wizard-header h2 {
    font-size: 2em;
    margin-bottom: 10px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.wizard-steps {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.wizard-step {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    background: rgba(255,255,255,0.2);
    border-radius: 25px;
    color: white;
    font-weight: 500;
    transition: all 0.3s;
}

.wizard-step.active {
    background: rgba(255,255,255,0.9);
    color: #856404;
    transform: scale(1.1);
}

.wizard-step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: white;
    color: #cebc40;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.wizard-step.active .wizard-step-number {
    background: #cebc40;
    color: white;
}

.search-box-container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.search-input-group {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-input-wrapper {
    flex: 1;
    min-width: 200px;
    position: relative;
}

.search-input-wrapper input,
.search-input-wrapper select {
    width: 100%;
    padding: 15px 20px;
    font-size: 16px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    transition: all 0.3s;
}

.search-input-wrapper input:focus,
.search-input-wrapper select:focus {
    border-color: #cebc40;
    box-shadow: 0 0 0 3px rgba(206, 188, 64, 0.1);
    outline: none;
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
    pointer-events: none;
}

.btn-add-product {
    background: linear-gradient(135deg, #cebc40 0%, #e3c70c 100%);
    color: white;
    border: none;
    padding: 15px 40px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(206, 188, 64, 0.4);
}

.btn-add-product:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(206, 188, 64, 0.6);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.product-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.product-card:hover {
    border-color: #10b981;
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.2);
}

.product-card.selected {
    border-color: #10b981;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
}

.product-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.product-code {
    background: #10b981;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.product-name {
    font-size: 16px;
    font-weight: 600;
    margin: 10px 0;
    color: #333;
}

.product-stock {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 10px 0;
}

.stock-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #4caf50;
}

.product-price {
    font-size: 18px;
    font-weight: 700;
    color: #856404;
    margin: 10px 0;
}

.quantity-selector {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.quantity-btn {
    width: 35px;
    height: 35px;
    border: 2px solid #cebc40;
    background: white;
    border-radius: 50%;
    color: #cebc40;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-btn:hover {
    background: #cebc40;
    color: white;
}

.quantity-input {
    width: 60px;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 8px;
}


.selected-products-container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    display: none;
}

.selected-products-container.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.selected-product-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 10px;
    transition: all 0.3s;
}

.selected-product-item:hover {
    background: #e9ecef;
}

.selected-product-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 15px;
}

.selected-product-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #cebc40 0%, #e3c70c 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.selected-product-details {
    flex: 1;
}

.selected-product-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.selected-product-meta {
    font-size: 14px;
    color: #666;
}

.selected-product-quantity {
    font-size: 24px;
    font-weight: 700;
    color: #856404;
    min-width: 60px;
    text-align: center;
}

.btn-remove {
    background: #f44336;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    transition: all 0.2s;
}

.btn-remove:hover {
    background: #d32f2f;
    transform: scale(1.1);
}

.summary-panel {
    background: linear-gradient(135deg, #cebc40 0%, #e3c70c 100%);
    padding: 25px;
    border-radius: 15px;
    color: white;
    text-align: center;
    margin-bottom: 20px;
}

.summary-value {
    font-size: 48px;
    font-weight: 700;
    margin: 10px 0;
}

.form-actions-fixed {
    position: sticky;
    bottom: 20px;
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
    display: flex;
    gap: 15px;
    justify-content: center;
    z-index: 100;
}

.btn-primary-large {
    background: linear-gradient(135deg, #cebc40 0%, #e3c70c 100%);
    color: white;
    border: none;
    padding: 18px 50px;
    font-size: 18px;
    font-weight: 600;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(206, 188, 64, 0.4);
}

.btn-primary-large:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(206, 188, 64, 0.6);
}

.btn-primary-large:disabled {
    background: #ccc;
    cursor: not-allowed;
    box-shadow: none;
}

.empty-state-modern {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-icon-large {
    font-size: 80px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .wizard-steps {
        flex-direction: column;
        align-items: center;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .search-input-group {
        flex-direction: column;
    }
    
    .selected-product-item {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="animation: slideDown 0.3s ease;">
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="wizard-container">
    <div class="wizard-header">
        <h2>📥 Registrar Entrada de Inventario</h2>
        <p style="opacity: 0.9;">Proceso simple en 3 pasos</p>
    </div>
    
    <div class="wizard-steps">
        <div class="wizard-step active" id="step1">
            <div class="wizard-step-number">1</div>
            <span>Seleccionar Productos</span>
        </div>
        <div class="wizard-step" id="step2">
            <div class="wizard-step-number">2</div>
            <span>Detalles de Entrada</span>
        </div>
        <div class="wizard-step" id="step3">
            <div class="wizard-step-number">3</div>
            <span>Confirmar</span>
        </div>
    </div>
</div>

<form method="POST" id="entradaForm" action="">
    <input type="hidden" name="action" value="create_entrada">
    <input type="hidden" name="productos" id="productosJson">
    
    <!-- Paso 1: Buscar y Seleccionar Productos -->
    <div class="search-box-container" id="paso1">
        <h3 style="margin-bottom: 20px; color: #333;">🔍 Buscar Productos Disponibles</h3>
        
        <div class="search-input-group">
            <div class="search-input-wrapper" style="flex: 2;">
                <input type="text" id="searchInput" placeholder="Buscar por nombre o código..." 
                       style="padding-left: 45px;" autocomplete="off">
                <span class="search-icon">🔍</span>
            </div>
        </div>
        
        <div class="products-grid" id="productsGrid">
            <?php 
            $productos_array = [];
            while ($prod = $productos_disponibles->fetch_assoc()): 
                $productos_array[] = $prod;
            ?>
                <div class="product-card" 
                     data-id="<?php echo $prod['id']; ?>"
                     data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                     data-codigo="<?php echo htmlspecialchars($prod['codigo']); ?>"
                     data-stock="<?php echo $prod['stock']; ?>"
                     data-precio="<?php echo $prod['precio']; ?>"
                     data-unidad="<?php echo htmlspecialchars($prod['unidad_medida'] ?? 'unidad'); ?>"
                     onclick="toggleProductSelection(this)">
                    
                    <div class="product-card-header">
                        <span class="product-code"><?php echo htmlspecialchars($prod['codigo']); ?></span>
                        <input type="checkbox" class="product-checkbox" style="width: 20px; height: 20px; cursor: pointer;">
                    </div>
                    
                    <div class="product-name"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                    
                    <div class="product-stock">
                        <span class="stock-indicator"></span>
                        <span>Stock: <strong><?php echo $prod['stock']; ?></strong> <?php echo htmlspecialchars($prod['unidad_medida'] ?? 'unidades'); ?></span>
                    </div>
                    
                    <div class="quantity-selector" style="display: none;">
                        <button type="button" class="quantity-btn" onclick="event.stopPropagation(); adjustQuantity(this, -1)">−</button>
                        <input type="number" class="quantity-input" value="1" min="1" 
                               onclick="event.stopPropagation();" onchange="updateProductQuantity(this)">
                        <button type="button" class="quantity-btn" onclick="event.stopPropagation(); adjustQuantity(this, 1)">+</button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <?php if (empty($productos_array)): ?>
            <div class="empty-state-modern">
                <div class="empty-state-icon-large">📦</div>
                <h3>No hay productos disponibles</h3>
                <p>Agrega productos activos para poder registrar entradas</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Paso 2: Productos Seleccionados -->
    <div class="selected-products-container" id="paso2">
        <h3 style="margin-bottom: 20px; color: #333;">✅ Productos Seleccionados</h3>
        
        <div class="summary-panel">
            <div>Total de Productos</div>
            <div class="summary-value" id="totalProductos">0</div>
            <div>productos seleccionados</div>
            <div style="margin-top: 15px; font-size: 1.2em;">
                Total: <strong id="totalValor">Q0.00</strong>
            </div>
        </div>
        
        <div id="selectedProductsList"></div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <h4 style="margin-bottom: 15px;">📋 Información de la Entrada</h4>
            
            <div class="form-group">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">📝 Observaciones (Opcional)</label>
                <textarea name="observaciones" id="observaciones" rows="3"
                          placeholder="Ej: Compra a proveedor, devolución de cliente, etc..."
                          style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; font-family: inherit;"></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions-fixed" id="formActions" style="display: none;">
        <button type="button" class="btn btn-secondary" onclick="resetForm()" style="padding: 18px 40px; font-size: 16px;">
            ❌ Cancelar
        </button>
        <button type="submit" class="btn-primary-large" id="btnProcesar" disabled>
            ✅ Registrar Entrada
        </button>
    </div>
</form>

<!-- Historial de entradas -->
<div class="table-container" style="margin-top: 40px;">
    <h3 style="margin-bottom: 20px;">📊 Historial de Entradas Recientes</h3>
    <div style="overflow-x: auto;">
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
                <?php if ($entradas->num_rows > 0): ?>
                    <?php while ($entrada = $entradas->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($entrada['numero_entrada']); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($entrada['fecha_entrada'])); ?></td>
                            <td><?php echo $entrada['total_productos']; ?></td>
                            <td><strong>Q<?php echo number_format($entrada['total_valor'] ?? 0, 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($entrada['usuario_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($entrada['observaciones'] ?? '-'); ?></td>
                            <td class="actions">
                                <a href="detalle_entrada.php?id=<?php echo $entrada['id']; ?>" 
                                   class="btn-icon" title="Ver Detalle"
                                   style="background: #cebc40; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none;">
                                    👁️ Ver
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="empty-state-modern" style="padding: 40px;">
                                <div class="empty-state-icon-large">📦</div>
                                <p>No hay entradas registradas</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
let productosSeleccionados = [];

// Búsqueda en tiempo real
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.product-card');
    
    cards.forEach(card => {
        const nombre = card.dataset.nombre.toLowerCase();
        const codigo = card.dataset.codigo.toLowerCase();
        
        if (nombre.includes(searchTerm) || codigo.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

function toggleProductSelection(card) {
    const checkbox = card.querySelector('.product-checkbox');
    const quantitySelector = card.querySelector('.quantity-selector');
    const productId = parseInt(card.dataset.id);
    
    // Toggle selección
    checkbox.checked = !checkbox.checked;
    card.classList.toggle('selected');
    
    if (checkbox.checked) {
        quantitySelector.style.display = 'flex';
        
        // Agregar a la lista
        const quantityInput = card.querySelector('.quantity-input');
        agregarProductoSeleccionado(card, parseInt(quantityInput.value));
    } else {
        quantitySelector.style.display = 'none';
        
        // Remover de la lista
        productosSeleccionados = productosSeleccionados.filter(p => p.id !== productId);
        actualizarVistaSeleccionados();
    }
}

function adjustQuantity(btn, delta) {
    const input = btn.parentElement.querySelector('.quantity-input');
    let newValue = parseInt(input.value) + delta;
    
    if (newValue < 1) newValue = 1;
    
    input.value = newValue;
    updateProductQuantity(input);
}

function updateProductQuantity(input) {
    const card = input.closest('.product-card');
    const productId = parseInt(card.dataset.id);
    const quantity = parseInt(input.value);
    
    // Actualizar en el array
    const index = productosSeleccionados.findIndex(p => p.id === productId);
    if (index >= 0) {
        productosSeleccionados[index].cantidad = quantity;
        actualizarVistaSeleccionados();
    }
}

function agregarProductoSeleccionado(card, cantidad) {
    const productId = parseInt(card.dataset.id);
    const precioFinal = parseFloat(card.dataset.precio);
    
    // Verificar si ya existe
    const existe = productosSeleccionados.find(p => p.id === productId);
    
    if (!existe) {
        productosSeleccionados.push({
            id: productId,
            codigo: card.dataset.codigo,
            nombre: card.dataset.nombre,
            cantidad: cantidad,
            precio: precioFinal,
            stock: parseInt(card.dataset.stock),
            unidad: card.dataset.unidad
        });
    } else {
        existe.cantidad = cantidad;
    }
    
    actualizarVistaSeleccionados();
}

function actualizarVistaSeleccionados() {
    const container = document.getElementById('paso2');
    const listContainer = document.getElementById('selectedProductsList');
    const totalElement = document.getElementById('totalProductos');
    const totalValorElement = document.getElementById('totalValor');
    const btnProcesar = document.getElementById('btnProcesar');
    const formActions = document.getElementById('formActions');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    
    if (productosSeleccionados.length === 0) {
        container.classList.remove('show');
        btnProcesar.disabled = true;
        formActions.style.display = 'none';
        step1.classList.add('active');
        step2.classList.remove('active');
        document.getElementById('productosJson').value = '';
        return;
    }
    
    // Mostrar sección
    container.classList.add('show');
    formActions.style.display = 'flex';
    btnProcesar.disabled = false;
    step2.classList.add('active');
    
    // Actualizar contador
    totalElement.textContent = productosSeleccionados.length;
    
    // Calcular total
    let totalValor = 0;
    productosSeleccionados.forEach(p => {
        totalValor += p.cantidad * p.precio;
    });
    totalValorElement.textContent = 'Q' + totalValor.toFixed(2);
    
    // Generar lista
    let html = '';
    productosSeleccionados.forEach((producto, index) => {
        html += `
            <div class="selected-product-item" style="animation: slideDown 0.3s ease;">
                <div class="selected-product-info">
                    <div class="selected-product-icon">📦</div>
                    <div class="selected-product-details">
                        <div class="selected-product-name">${producto.nombre}</div>
                        <div class="selected-product-meta">
                            <span style="background: #cebc40; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-right: 8px;">
                                ${producto.codigo}
                            </span>
                            Stock actual: <strong>${producto.stock}</strong> ${producto.unidad}
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="quantity-btn" onclick="ajustarCantidadSeleccionado(${index}, -1)" title="Disminuir">−</button>
                    <input type="number" class="quantity-input" value="${producto.cantidad}" 
                           min="1" 
                           onchange="cambiarCantidadSeleccionado(${index}, this.value)"
                           onclick="this.select()"
                           style="width: 70px;">
                    <button type="button" class="quantity-btn" onclick="ajustarCantidadSeleccionado(${index}, 1)" title="Aumentar">+</button>
                </div>
                <button type="button" class="btn-remove" onclick="removerProducto(${index})" title="Eliminar">
                    ✕
                </button>
            </div>
        `;
    });
    
    listContainer.innerHTML = html;
    
    // Actualizar JSON
    document.getElementById('productosJson').value = JSON.stringify(productosSeleccionados);
    
    // Scroll suave a la sección
    setTimeout(() => {
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

function removerProducto(index) {
    if (confirm('¿Deseas quitar este producto de la lista?')) {
        const producto = productosSeleccionados[index];
        
        // Desmarcar tarjeta
        const card = document.querySelector(`.product-card[data-id="${producto.id}"]`);
        if (card) {
            card.classList.remove('selected');
            card.querySelector('.product-checkbox').checked = false;
            card.querySelector('.quantity-selector').style.display = 'none';
        }
        
        productosSeleccionados.splice(index, 1);
        actualizarVistaSeleccionados();
    }
}

function ajustarCantidadSeleccionado(index, delta) {
    const producto = productosSeleccionados[index];
    let nuevaCantidad = producto.cantidad + delta;
    
    if (nuevaCantidad < 1) {
        alert('⚠️ La cantidad mínima es 1');
        return;
    }
    
    productosSeleccionados[index].cantidad = nuevaCantidad;
    
    // Actualizar también en la tarjeta
    const card = document.querySelector(`.product-card[data-id="${producto.id}"]`);
    if (card) {
        card.querySelector('.quantity-input').value = nuevaCantidad;
    }
    
    actualizarVistaSeleccionados();
}

function cambiarCantidadSeleccionado(index, valor) {
    const producto = productosSeleccionados[index];
    let nuevaCantidad = parseInt(valor);
    
    if (isNaN(nuevaCantidad) || nuevaCantidad < 1) {
        alert('⚠️ La cantidad mínima es 1');
        nuevaCantidad = 1;
    }
    
    productosSeleccionados[index].cantidad = nuevaCantidad;
    
    // Actualizar también en la tarjeta
    const card = document.querySelector(`.product-card[data-id="${producto.id}"]`);
    if (card) {
        card.querySelector('.quantity-input').value = nuevaCantidad;
    }
    
    actualizarVistaSeleccionados();
}

function resetForm() {
    if (confirm('¿Estás seguro? Se perderán todos los productos seleccionados.')) {
        // Limpiar selecciones
        document.querySelectorAll('.product-card.selected').forEach(card => {
            card.classList.remove('selected');
            card.querySelector('.product-checkbox').checked = false;
            card.querySelector('.quantity-selector').style.display = 'none';
            card.querySelector('.quantity-input').value = 1;
        });
        
        productosSeleccionados = [];
        actualizarVistaSeleccionados();
        
        // Limpiar campos
        document.getElementById('observaciones').value = '';
        document.getElementById('searchInput').value = '';
        
        // Scroll al inicio
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Confirmación antes de enviar
document.getElementById('entradaForm').addEventListener('submit', function(e) {
    const total = productosSeleccionados.length;
    
    if (!confirm(`¿Confirmar entrada de ${total} producto(s)?`)) {
        e.preventDefault();
    }
});

// Auto-focus en búsqueda al cargar
window.addEventListener('load', function() {
    document.getElementById('searchInput').focus();
});
</script>

<?php closeConnection($conn); ?>
