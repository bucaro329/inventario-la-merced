<?php
$page_title = 'Gestión de Usuarios - La Merced';
require_once 'config/database.php';
require_once 'config/auth.php';
requireAdmin();

$conn = getConnection();
$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = trim($_POST['username']);
                $password = $_POST['password'];
                $nombre_completo = trim($_POST['nombre_completo']);
                $email = trim($_POST['email'] ?? '');
                $rol = trim($_POST['rol']);
                
                if (empty($password) || strlen($password) < 6) {
                    $mensaje = "La contraseña debe tener al menos 6 caracteres";
                    $tipo_mensaje = "error";
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO usuarios (username, password, nombre_completo, email, rol) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $username, $password_hash, $nombre_completo, $email, $rol);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Usuario agregado exitosamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al agregar usuario: " . $stmt->error;
                        $tipo_mensaje = "error";
                    }
                    $stmt->close();
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $username = trim($_POST['username']);
                $nombre_completo = trim($_POST['nombre_completo']);
                $email = trim($_POST['email'] ?? '');
                $rol = trim($_POST['rol']);
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                $stmt = $conn->prepare("UPDATE usuarios SET username=?, nombre_completo=?, email=?, rol=?, activo=? WHERE id=?");
                $stmt->bind_param("ssssii", $username, $nombre_completo, $email, $rol, $activo, $id);
                
                if ($stmt->execute()) {
                    // Si se proporcionó nueva contraseña
                    if (!empty($_POST['password'])) {
                        $password = $_POST['password'];
                        if (strlen($password) >= 6) {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt2 = $conn->prepare("UPDATE usuarios SET password=? WHERE id=?");
                            $stmt2->bind_param("si", $password_hash, $id);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                    }
                    
                    $mensaje = "Usuario actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar usuario: " . $stmt->error;
                    $tipo_mensaje = "error";
                }
                $stmt->close();
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                // No permitir eliminar el usuario actual
                if ($id == $_SESSION['user_id']) {
                    $mensaje = "No puede desactivar su propio usuario";
                    $tipo_mensaje = "error";
                } else {
                    $stmt = $conn->prepare("UPDATE usuarios SET activo=0 WHERE id=?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Usuario desactivado exitosamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al desactivar usuario: " . $stmt->error;
                        $tipo_mensaje = "error";
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Obtener usuarios
$usuarios = $conn->query("SELECT * FROM usuarios ORDER BY nombre_completo");

// Obtener usuario para editar
$usuario_editar = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM usuarios WHERE id = $id");
    $usuario_editar = $result->fetch_assoc();
}
require_once 'includes/header.php';
?>
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h2>Gestión de Usuarios</h2>
                <button class="btn btn-primary" onclick="toggleForm()"><?php echo $usuario_editar ? 'Cancelar Edición' : '➕ Agregar Usuario'; ?></button>
            </div>

            <!-- Formulario de usuario -->
            <div class="form-container" id="userForm" style="display: <?php echo $usuario_editar ? 'block' : 'none'; ?>;">
                <h3><?php echo $usuario_editar ? 'Editar Usuario' : 'Nuevo Usuario'; ?></h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $usuario_editar ? 'edit' : 'add'; ?>">
                    <?php if ($usuario_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $usuario_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Usuario *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['username']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Contraseña <?php echo $usuario_editar ? '(dejar vacío para no cambiar)' : '*'; ?></label>
                            <input type="password" id="password" name="password" <?php echo !$usuario_editar ? 'required' : ''; ?> 
                                   minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre_completo">Nombre Completo *</label>
                            <input type="text" id="nombre_completo" name="nombre_completo" required 
                                   value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['nombre_completo']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['email'] ?? '') : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rol">Rol *</label>
                            <select id="rol" name="rol" required>
                                <option value="operador" <?php echo (!$usuario_editar || $usuario_editar['rol'] == 'operador') ? 'selected' : ''; ?>>Operador</option>
                                <option value="admin" <?php echo ($usuario_editar && $usuario_editar['rol'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                        </div>
                        
                        <?php if ($usuario_editar): ?>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="activo" value="1" <?php echo $usuario_editar['activo'] ? 'checked' : ''; ?>>
                                    Usuario Activo
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $usuario_editar ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($usuario_editar): ?>
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabla de usuarios -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($usuarios->num_rows > 0): ?>
                            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email'] ?? ''); ?></td>
                                    <td><?php echo ucfirst($usuario['rol']); ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo $usuario['activo'] ? 'success' : 'warning'; ?>">
                                            <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="?edit=<?php echo $usuario['id']; ?>" class="btn-icon" title="Editar">✏️</a>
                                        <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Está seguro de desactivar este usuario?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn-icon" title="Desactivar">🗑️</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No hay usuarios registrados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
<script>
    function toggleForm() {
        const form = document.getElementById('userForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            window.scrollTo({ top: form.offsetTop, behavior: 'smooth' });
        }
    }
</script>
<?php closeConnection($conn); ?>

