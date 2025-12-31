<?php
// Sistema de autenticación
session_start();

// Verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Obtener información del usuario actual
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    require_once 'database.php';
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT id, username, nombre_completo, email, rol FROM usuarios WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    closeConnection($conn);
    return $user;
}

// Verificar si el usuario es administrador
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['rol'] === 'admin';
}

// Requerir login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Requerir rol de administrador
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php?error=no_permission');
        exit;
    }
}

// Cerrar sesión
function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

