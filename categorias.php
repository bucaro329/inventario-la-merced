<?php
$page_title = 'Gestión de Categorías - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion'] ?? '');
                
                $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
                $stmt->bind_param("ss", $nombre, $descripcion);
                
                if ($stmt->execute()) {
                    $mensaje = "Categoría agregada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al agregar categoría: " . $stmt->error;
                    $tipo_mensaje = "error";
                }
                $stmt->close();
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion'] ?? '');
                
                $stmt = $conn->prepare("UPDATE categorias SET nombre=?, descripcion=? WHERE id=?");
                $stmt->bind_param("ssi", $nombre, $descripcion, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Categoría actualizada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar categoría: " . $stmt->error;
                    $tipo_mensaje = "error";
                }
                $stmt->close();
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE categorias SET activo=0 WHERE id=?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Categoría eliminada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al eliminar categoría: " . $stmt->error;
                    $tipo_mensaje = "error";
                }
                $stmt->close();
                break;
        }
    }
}

// Obtener categorías
$categorias = $conn->query("SELECT c.*, COUNT(p.id) as total_productos FROM categorias c LEFT JOIN productos p ON c.id = p.categoria_id WHERE c.activo = 1 GROUP BY c.id ORDER BY c.nombre");

// Obtener categoría para editar
$categoria_editar = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM categorias WHERE id = $id AND activo = 1");
    $categoria_editar = $result->fetch_assoc();
}
require_once 'includes/header.php';
?>
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h2>Gestión de Categorías</h2>
                <button class="btn btn-primary" onclick="toggleForm()"><?php echo $categoria_editar ? 'Cancelar Edición' : '➕ Agregar Categoría'; ?></button>
            </div>

            <!-- Formulario de categoría -->
            <div class="form-container" id="categoryForm" style="display: <?php echo $categoria_editar ? 'block' : 'none'; ?>;">
                <h3><?php echo $categoria_editar ? 'Editar Categoría' : 'Nueva Categoría'; ?></h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $categoria_editar ? 'edit' : 'add'; ?>">
                    <?php if ($categoria_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $categoria_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required 
                               value="<?php echo $categoria_editar ? htmlspecialchars($categoria_editar['nombre']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3"><?php echo $categoria_editar ? htmlspecialchars($categoria_editar['descripcion']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $categoria_editar ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($categoria_editar): ?>
                            <a href="categorias.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabla de categorías -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Productos</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($categorias->num_rows > 0): 
                        ?>
                            <?php while ($categoria = $categorias->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $categoria['id']; ?></td>
                                    <td><?php echo htmlspecialchars($categoria['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></td>
                                    <td><?php echo $categoria['total_productos']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($categoria['fecha_creacion'])); ?></td>
                                    <td class="actions">
                                        <a href="?edit=<?php echo $categoria['id']; ?>" class="btn-icon" title="Editar">✏️</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar esta categoría?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $categoria['id']; ?>">
                                            <button type="submit" class="btn-icon" title="Eliminar">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay categorías registradas</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
<script>
    function toggleForm() {
        const form = document.getElementById('categoryForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            window.scrollTo({ top: form.offsetTop, behavior: 'smooth' });
        }
    }
</script>
<?php closeConnection($conn); ?>

