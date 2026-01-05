<?php
$page_title = 'Importar Productos - La Merced';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
requireLogin();

// Asegurar que las rutas de CSS funcionen correctamente
$base_path = dirname(__DIR__);

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';
$productos_importados = 0;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_excel'])) {
    $archivo = $_FILES['archivo_excel'];
    
    if ($archivo['error'] == UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if ($extension == 'csv' || $extension == 'xls' || $extension == 'xlsx') {
            // Leer archivo CSV
            if ($extension == 'csv') {
                $handle = fopen($archivo['tmp_name'], 'r');
                $linea = 0;
                
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $linea++;
                    if ($linea == 1) continue; // Saltar encabezados
                    
                    if (count($data) >= 6) {
                        $codigo = trim($data[0]);
                        $nombre = trim($data[1]);
                        $descripcion = trim($data[2] ?? '');
                        $precio = floatval($data[3] ?? 0);
                        $stock = intval($data[4] ?? 0);
                        $unidad_medida = trim($data[5] ?? 'UNIDAD');
                        $categoria_id = !empty($data[6]) ? intval($data[6]) : null;
                        $estado = !empty($data[7]) ? trim($data[7]) : 'activo';
                        
                        if (!empty($codigo) && !empty($nombre)) {
                            // Verificar si el código ya existe
                            $check = $conn->query("SELECT id FROM productos WHERE codigo = '" . $conn->real_escape_string($codigo) . "'");
                            
                            if ($check->num_rows == 0) {
                                $stmt = $conn->prepare("INSERT INTO productos (codigo, nombre, descripcion, precio, stock, unidad_medida, categoria_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("sssddsis", $codigo, $nombre, $descripcion, $precio, $stock, $unidad_medida, $categoria_id, $estado);
                                
                                if ($stmt->execute()) {
                                    $productos_importados++;
                                } else {
                                    $errores[] = "Línea $linea: " . $stmt->error;
                                }
                                $stmt->close();
                            } else {
                                $errores[] = "Línea $linea: El código '$codigo' ya existe";
                            }
                        }
                    }
                }
                fclose($handle);
            } else {
                // Para XLS/XLSX, mostrar instrucciones para convertir a CSV
                $mensaje = "Por favor, convierta el archivo Excel a CSV antes de importar. Use 'Guardar como' y seleccione formato CSV.";
                $tipo_mensaje = "error";
            }
            
            if ($productos_importados > 0) {
                $mensaje = "Se importaron exitosamente $productos_importados productos.";
                $tipo_mensaje = "success";
                if (!empty($errores)) {
                    $mensaje .= " Hubo " . count($errores) . " errores.";
                }
            } else if (empty($errores)) {
                $mensaje = "No se encontraron productos válidos para importar.";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Formato de archivo no válido. Use CSV, XLS o XLSX.";
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Error al subir el archivo.";
        $tipo_mensaje = "error";
    }
}

// Obtener categorías para el ejemplo
$categorias = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// Corregir rutas para CSS e imágenes desde la subcarpeta imports/
$base_url = '../';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<style>
    /* Asegurar que las rutas funcionen desde imports/ */
    .sidebar-header img,
    .logo_menu {
        content: url('../img/logo_menu.png');
    }
</style>
            <div class="page-header">
                <h2>📥 Importar Productos desde Excel/CSV</h2>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                    <?php if (!empty($errores) && count($errores) <= 10): ?>
                        <ul style="margin-top: 10px;">
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h3>Subir Archivo</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="archivo_excel">Seleccionar archivo (CSV, XLS, XLSX)</label>
                        <input type="file" id="archivo_excel" name="archivo_excel" accept=".csv,.xls,.xlsx" required>
                        <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                            El archivo debe tener las siguientes columnas en orden: Código, Nombre, Descripción, Precio, Stock, Unidad de Medida, Categoría ID (opcional), Estado (opcional)
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">📥 Importar Productos</button>
                        <a href="../productos.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="form-container">
                <h3>📋 Formato del Archivo CSV</h3>
                <p>El archivo CSV debe tener la siguiente estructura (la primera fila son los encabezados):</p>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Unidad de Medida</th>
                                <th>Categoría ID</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PROD001</td>
                                <td>Producto Ejemplo</td>
                                <td>Descripción del producto</td>
                                <td>25.50</td>
                                <td>100</td>
                                <td>UNIDAD</td>
                                <td>1</td>
                                <td>activo</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #f7bc00;">
                    <strong>⚠️ Notas importantes:</strong>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <li>El <strong>Código</strong> debe ser único. Si ya existe, se omitirá ese producto.</li>
                        <li><strong>Nombre</strong> es obligatorio.</li>
                        <li><strong>Precio</strong> y <strong>Stock</strong> deben ser números válidos.</li>
                        <li><strong>Unidad de Medida</strong> puede ser: UNIDAD, KG, LITRO, CAJA, METRO</li>
                        <li><strong>Categoría ID</strong> es opcional. Debe ser el ID numérico de una categoría existente.</li>
                        <li><strong>Estado</strong> puede ser "activo" o "inactivo" (por defecto: activo)</li>
                        <li>Para archivos Excel (.xls, .xlsx), primero conviértelos a CSV usando "Guardar como" en Excel.</li>
                    </ul>
                </div>

                <div style="margin-top: 20px;">
                    <h4>Categorías disponibles:</h4>
                    <table class="data-table" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cat = $categorias->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $cat['id']; ?></td>
                                    <td><?php echo htmlspecialchars($cat['nombre']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
<?php closeConnection($conn); ?>

