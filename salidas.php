<?php
$page_title = 'Salidas de Inventario - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();
$current_user = getCurrentUser();
$mensaje = '';
$tipo_mensaje = '';

// Procesar salida
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_salida') {
    $productos = json_decode($_POST['productos'], true);
    
    if (empty($productos)) {
        $mensaje = "Debe agregar al menos un producto";
        $tipo_mensaje = "error";
    } else {
        $conn->begin_transaction();
        
        try {
            // Generar número de salida
            $numero_salida = 'SAL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $motivo = trim($_POST['motivo'] ?? 'uso_interno');
            $observaciones = trim($_POST['observaciones'] ?? '');
            $usuario_id = $current_user['id'];
            
            // Insertar salida
            $stmt = $conn->prepare("INSERT INTO salidas (numero_salida, fecha_salida, motivo, observaciones, usuario_id) VALUES (?, NOW(), ?, ?, ?)");
            $stmt->bind_param("sssi", $numero_salida, $motivo, $observaciones, $usuario_id);
            $stmt->execute();
            $salida_id = $conn->insert_id;
            
            // Insertar detalles y actualizar stock
            foreach ($productos as $p) {
                // Verificar stock disponible
                $result = $conn->query("SELECT stock FROM productos WHERE id = " . intval($p['id']));
                $stock_actual = $result->fetch_assoc()['stock'];
                
                if ($stock_actual < $p['cantidad']) {
                    throw new Exception("Stock insuficiente para el producto: " . $p['nombre']);
                }
                
                // Insertar detalle
                $stmt = $conn->prepare("INSERT INTO detalle_salidas (salida_id, producto_id, cantidad) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $salida_id, $p['id'], $p['cantidad']);
                $stmt->execute();
                
                // Obtener stock antes
                $stock_antes = $stock_actual;
                $stock_despues = $stock_antes - $p['cantidad'];
                
                // Actualizar stock
                $stmt = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->bind_param("ii", $p['cantidad'], $p['id']);
                $stmt->execute();
                
                // Registrar movimiento
                $stmt = $conn->prepare("INSERT INTO movimientos (producto_id, tipo_movimiento, referencia_id, referencia_tipo, cantidad, stock_antes, stock_despues, fecha_movimiento, usuario_id, observaciones) VALUES (?, 'salida', ?, 'salida', ?, ?, ?, NOW(), ?, ?)");
                $obs_mov = "Motivo: " . $motivo . ($observaciones ? " - " . $observaciones : "");
                $stmt->bind_param("iiiiiis", $p['id'], $salida_id, $p['cantidad'], $stock_antes, $stock_despues, $usuario_id, $obs_mov);
                $stmt->execute();
            }
            
            $conn->commit();
            header("Location: salidas.php?success=1&id=" . $salida_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al procesar la salida: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

if (isset($_GET['success'])) {
    $mensaje = "✅ Salida registrada exitosamente con el número: " . ($_GET['id'] ?? '');
    $tipo_mensaje = "success";
}

// Obtener productos disponibles con más información
$productos_disponibles = $conn->query("
    SELECT id, codigo, nombre, stock, unidad_medida, categoria_id 
    FROM productos 
    WHERE estado = 'activo' AND stock > 0 
    ORDER BY nombre
");

// Obtener salidas recientes
$salidas = $conn->query("
    SELECT s.*, u.nombre_completo as usuario_nombre,
           COUNT(d.id) as total_productos,
           SUM(d.cantidad) as total_cantidad
    FROM salidas s
    LEFT JOIN usuarios u ON s.usuario_id = u.id
    LEFT JOIN detalle_salidas d ON s.id = d.salida_id
    GROUP BY s.id
    ORDER BY s.fecha_salida DESC
    LIMIT 50
");
require_once 'includes/header.php';
?>

<style>
/* Estilos mejorados para una interfaz más intuitiva */
.wizard-container {
    background: linear-gradient(135deg, #960f1c 0%, #960f1c 100%);
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
    color: #960f1c;
    transform: scale(1.1);
}

.wizard-step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: white;
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.wizard-step.active .wizard-step-number {
    background: #667eea;
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
}

.search-input-wrapper {
    flex: 1;
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
    border-color: #960f1c;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
    background: linear-gradient(135deg, #960f1c 0%, #960f1c 100%);
    color: white;
    border: none;
    padding: 15px 40px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-add-product:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.btn-add-product:active {
    transform: translateY(0);
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
    border-color: #960f1c;
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
}

.product-card.selected {
    border-color: #960f1c;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.product-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.product-code {
    background: #960f1c;
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

.stock-indicator.low {
    background: #ff9800;
}

.stock-indicator.critical {
    background: #f44336;
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
    border: 2px solid #960f1c;
    background: white;
    border-radius: 50%;
    color: #960f1c;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-btn:hover {
    background: #960f1c;
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
    background: linear-gradient(135deg, #960f1c 0%, #960f1c 100%);
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
    color: #960f1c;
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
    background: linear-gradient(135deg, #960f1c 0%, #960f1c 100%);
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
    background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
    color: white;
    border: none;
    padding: 18px 50px;
    font-size: 18px;
    font-weight: 600;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
}

.btn-primary-large:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
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
}
</style>

<?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="animation: slideDown 0.3s ease;">
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="wizard-container">
    <div class="wizard-header">
        <h2>📤 Registrar Salida de Inventario</h2>
        <p style="opacity: 0.9;">Proceso simple en 3 pasos</p>
    </div>
    
    <div class="wizard-steps">
        <div class="wizard-step active" id="step1">
            <div class="wizard-step-number">1</div>
            <span>Seleccionar Productos</span>
        </div>
        <div class="wizard-step" id="step2">
            <div class="wizard-step-number">2</div>
            <span>Detalles de Salida</span>
        </div>
        <div class="wizard-step" id="step3">
            <div class="wizard-step-number">3</div>
            <span>Confirmar</span>
        </div>
    </div>
</div>

<form method="POST" id="salidaForm" action="">
    <input type="hidden" name="action" value="create_salida">
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
            
            <div class="search-input-wrapper">
                <select id="filterStock" style="cursor: pointer;">
                    <option value="all">Todos los productos</option>
                    <option value="high">Stock alto (50+)</option>
                    <option value="medium">Stock medio (10-49)</option>
                    <option value="low">Stock bajo (-10)</option>
                </select>
            </div>
        </div>
        
        <div class="products-grid" id="productsGrid">
            <?php 
            $productos_array = [];
            while ($prod = $productos_disponibles->fetch_assoc()): 
                $productos_array[] = $prod;
                $stock_class = $prod['stock'] > 50 ? '' : ($prod['stock'] > 10 ? 'low' : 'critical');
            ?>
                <div class="product-card" 
                     data-id="<?php echo $prod['id']; ?>"
                     data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                     data-codigo="<?php echo htmlspecialchars($prod['codigo']); ?>"
                     data-stock="<?php echo $prod['stock']; ?>"
                     data-unidad="<?php echo htmlspecialchars($prod['unidad_medida'] ?? 'unidad'); ?>"
                     onclick="toggleProductSelection(this)">
                    
                    <div class="product-card-header">
                        <span class="product-code"><?php echo htmlspecialchars($prod['codigo']); ?></span>
                        <input type="checkbox" class="product-checkbox" style="width: 20px; height: 20px; cursor: pointer;">
                    </div>
                    
                    <div class="product-name"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                    
                    <div class="product-stock">
                        <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                        <span>Stock: <strong><?php echo $prod['stock']; ?></strong> <?php echo htmlspecialchars($prod['unidad_medida'] ?? 'unidades'); ?></span>
                    </div>
                    
                    <div class="quantity-selector" style="display: none;">
                        <button type="button" class="quantity-btn" onclick="event.stopPropagation(); adjustQuantity(this, -1)">−</button>
                        <input type="number" class="quantity-input" value="1" min="1" max="<?php echo $prod['stock']; ?>" 
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
                <p>Agrega productos con stock disponible para poder registrar salidas</p>
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
        </div>
        
        <div id="selectedProductsList"></div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <h4 style="margin-bottom: 15px;">📋 Información de la Salida</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">🎯 Motivo de Salida *</label>
                    <select name="motivo" id="motivo" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                        <option value="venta">🛒 Venta</option>
                        <option value="uso_interno">🏢 Uso Interno</option>
                        <option value="ajuste">⚖️ Ajuste de Inventario</option>
                        <option value="danio">⚠️ Daño</option>
                        <option value="perdida">❌ Pérdida</option>
                        <option value="otro">📌 Otro</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">📝 Observaciones (Opcional)</label>
                    <input type="text" name="observaciones" id="observaciones" 
                           placeholder="Ej: Cliente Juan Pérez, Producto defectuoso..."
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions-fixed" id="formActions" style="display: none;">
        <button type="button" class="btn btn-secondary" onclick="resetForm()" style="padding: 18px 40px; font-size: 16px;">
            ❌ Cancelar
        </button>
        <button type="submit" class="btn-primary-large" id="btnProcesar" disabled>
            ✅ Registrar Salida
        </button>
    </div>
</form>

<!-- Historial de salidas -->
<div class="table-container" style="margin-top: 40px;">
    <h3 style="margin-bottom: 20px;">📊 Historial de Salidas Recientes</h3>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Motivo</th>
                    <th>Productos</th>
                    <th>Cantidad Total</th>
                    <th>Usuario</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($salidas->num_rows > 0): ?>
                    <?php while ($salida = $salidas->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($salida['numero_salida']); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($salida['fecha_salida'])); ?></td>
                            <td><?php 
                                $motivos = [
                                    'venta' => '🛒 Venta',
                                    'uso_interno' => '🏢 Uso Interno',
                                    'ajuste' => '⚖️ Ajuste',
                                    'danio' => '⚠️ Daño',
                                    'perdida' => '❌ Pérdida',
                                    'otro' => '📌 Otro'
                                ];
                                echo $motivos[$salida['motivo']] ?? $salida['motivo'];
                            ?></td>
                            <td><?php echo $salida['total_productos']; ?></td>
                            <td><strong><?php echo $salida['total_cantidad'] ?? 0; ?></strong></td>
                            <td><?php echo htmlspecialchars($salida['usuario_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($salida['observaciones'] ?? '-'); ?></td>
                            <td class="actions">
                                <a href="detalle_salida.php?id=<?php echo $salida['id']; ?>" 
                                   class="btn-icon" title="Ver Detalle"
                                   style="background: #667eea; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none;">
                                    👁️ Ver
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="empty-state-modern" style="padding: 40px;">
                                <div class="empty-state-icon-large">📦</div>
                                <p>No hay salidas registradas</p>
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

// Filtro por stock
document.getElementById('filterStock').addEventListener('change', function(e) {
    const filter = e.target.value;
    const cards = document.querySelectorAll('.product-card');
    
    cards.forEach(card => {
        const stock = parseInt(card.dataset.stock);
        let show = true;
        
        if (filter === 'high' && stock <= 50) show = false;
        if (filter === 'medium' && (stock < 10 || stock > 49)) show = false;
        if (filter === 'low' && stock >= 10) show = false;
        
        card.style.display = show ? 'block' : 'none';
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
    const card = btn.closest('.product-card');
    const max = parseInt(card.dataset.stock);
    let newValue = parseInt(input.value) + delta;
    
    if (newValue < 1) newValue = 1;
    if (newValue > max) {
        alert(`⚠️ La cantidad máxima disponible es ${max}`);
        newValue = max;
    }
    
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
    
    // Verificar si ya existe
    const existe = productosSeleccionados.find(p => p.id === productId);
    
    if (!existe) {
        productosSeleccionados.push({
            id: productId,
            codigo: card.dataset.codigo,
            nombre: card.dataset.nombre,
            cantidad: cantidad,
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
    
    // Generar lista
    let html = '';
    productosSeleccionados.forEach((producto, index) => {
        const porcentaje = (producto.cantidad / producto.stock * 100).toFixed(0);
        const colorBarra = porcentaje > 75 ? '#f44336' : porcentaje > 50 ? '#ff9800' : '#4caf50';
        
        html += `
            <div class="selected-product-item" style="animation: slideDown 0.3s ease;">
                <div class="selected-product-info">
                    <div class="selected-product-icon">📦</div>
                    <div class="selected-product-details">
                        <div class="selected-product-name">${producto.nombre}</div>
                        <div class="selected-product-meta">
                            <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-right: 8px;">
                                ${producto.codigo}
                            </span>
                            Stock disponible: <strong>${producto.stock}</strong> ${producto.unidad}
                        </div>
                        <div style="margin-top: 8px;">
                            <div style="background: #e0e0e0; height: 6px; border-radius: 3px; overflow: hidden;">
                                <div style="background: ${colorBarra}; height: 100%; width: ${porcentaje}%; transition: width 0.3s;"></div>
                            </div>
                            <small style="color: #666;">${porcentaje}% del stock</small>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="quantity-btn" onclick="ajustarCantidadSeleccionado(${index}, -1)" title="Disminuir">−</button>
                    <input type="number" class="quantity-input" value="${producto.cantidad}" 
                           min="1" max="${producto.stock}" 
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
    
    if (nuevaCantidad > producto.stock) {
        alert(`⚠️ La cantidad máxima disponible es ${producto.stock}`);
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
    
    if (nuevaCantidad > producto.stock) {
        alert(`⚠️ La cantidad máxima disponible es ${producto.stock}`);
        nuevaCantidad = producto.stock;
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
        document.getElementById('motivo').value = 'venta';
        document.getElementById('observaciones').value = '';
        document.getElementById('searchInput').value = '';
        
        // Scroll al inicio
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Confirmación antes de enviar
document.getElementById('salidaForm').addEventListener('submit', function(e) {
    const total = productosSeleccionados.length;
    const motivo = document.getElementById('motivo').options[document.getElementById('motivo').selectedIndex].text;
    
    if (!confirm(`¿Confirmar salida de ${total} producto(s) por motivo: ${motivo}?`)) {
        e.preventDefault();
    }
});

// Mensaje de confirmación al cargar si hay éxito
<?php if (isset($_GET['success'])): ?>
    setTimeout(() => {
        if (confirm('✅ Salida registrada exitosamente. ¿Deseas registrar otra salida?')) {
            window.location.href = 'salidas.php';
        }
    }, 500);
<?php endif; ?>

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter para enviar formulario
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        if (productosSeleccionados.length > 0) {
            document.getElementById('salidaForm').requestSubmit();
        }
    }
    
    // Escape para cancelar
    if (e.key === 'Escape') {
        document.getElementById('searchInput').blur();
    }
});

// Auto-focus en búsqueda al cargar
window.addEventListener('load', function() {
    document.getElementById('searchInput').focus();
});
</script>

<?php closeConnection($conn); ?>