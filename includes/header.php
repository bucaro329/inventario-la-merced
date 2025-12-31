<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();
$current_user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistema de Inventario - La Merced'; ?></title>
    <link rel="icon" href="img/logo_menu.png" type="image/png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="img/logo_menu.png" alt="Logo" class="logo_menu">
        </div>
        
        <div class="sidebar-user">
            <div class="sidebar-user-info">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($current_user['nombre_completo'], 0, 1)); ?>
                </div>
                <div>
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($current_user['nombre_completo']); ?></div>
                    <div class="sidebar-user-role"><?php echo ucfirst($current_user['rol']); ?></div>
                </div>
            </div>
            <a href="logout.php" class="sidebar-logout">🚪 Cerrar Sesión</a>
        </div>
        
        <nav class="sidebar-menu">
            <a href="index.php" class="sidebar-menu-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <span class="sidebar-menu-item-icon">🏠</span>
                <span>Inicio</span>
            </a>


            <a href="productos.php" class="sidebar-menu-item <?php echo $current_page == 'productos.php' ? 'active' : ''; ?>">
                <span class="sidebar-menu-item-icon">📦</span>
                <span>Productos</span>
            </a>

            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
            <a href="categorias.php" class="sidebar-menu-item <?php echo $current_page == 'categorias.php' ? 'active' : ''; ?>">
                <span class="sidebar-menu-item-icon">🏷️</span>
                <span>Categorías de Productos</span>
            </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
            <a href="entradas.php" class="sidebar-menu-item <?php echo $current_page == 'entradas.php' ? 'active' : ''; ?>">
                <span class="sidebar-menu-item-icon">📥</span>
                <span>Ingresos</span>
            </a>
            <?php endif; ?>


            <a href="salidas.php" class="sidebar-menu-item <?php echo $current_page == 'salidas.php' ? 'active' : ''; ?>">
                <span class="sidebar-menu-item-icon">📤</span>
                <span>Ventas</span>
            </a>


            <a href="kardex.php" class="sidebar-menu-item <?php echo $current_page == 'kardex.php' ? 'active' : ''; ?>">
                <span class="sidebar-menu-item-icon">📋</span>
                <span>Movimientos</span>
            </a>


            <a href="reportes.php" class="sidebar-menu-item <?php echo $current_page == 'reportes.php' ? 'active' : ''; ?>">
                <span class="sidebar-menu-item-icon">📊</span>
                <span>Reportes de Inventario</span>
            </a>


            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="usuarios.php" class="sidebar-menu-item <?php echo $current_page == 'usuarios.php' ? 'active' : ''; ?>">
                    <span class="sidebar-menu-item-icon">👥</span>
                    <span>Usuarios</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="top-bar">
            <div>
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <span class="top-bar-title"><?php echo $page_title ?? 'Sistema de Inventario'; ?></span>
            </div>
        </div>
        
        <div class="content-area">
