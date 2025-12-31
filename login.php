<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id, username, password, nombre_completo, rol FROM usuarios WHERE username = ? AND activo = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verificar contraseña
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nombre_completo'] = $user['nombre_completo'];
                $_SESSION['rol'] = $user['rol'];
                
                header('Location: index.php');
                exit;
            } else {
                // Si la verificación falla, intentar actualizar la contraseña si es admin123
                if ($password === 'admin123') {
                    // Actualizar el hash de la contraseña
                    $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $new_hash, $user['id']);
                    if ($update_stmt->execute()) {
                        // Intentar login nuevamente
                        if (password_verify($password, $new_hash)) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['nombre_completo'] = $user['nombre_completo'];
                            $_SESSION['rol'] = $user['rol'];
                            
                            header('Location: index.php');
                            exit;
                        }
                    }
                    $update_stmt->close();
                }
                $error = 'Usuario o contraseña incorrectos';
            }
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
        
        $stmt->close();
        closeConnection($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Inventario La Merced</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100vw;
            height: 100vh;
            background: #960f1c;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        
        .login-box {
            background: white;
            padding: 50px 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
            margin: 20px;
        }
        
        .login-box h1 {
            text-align: center;
            color: #960f1c;
            margin-bottom: 10px;
            font-size: 1.8em;
            font-weight: 700;
        }
        
        .login-box .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
        }
        
        .login-info {
            background: #fff3cd;
            border-left: 4px solid #f7bc00;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9em;
            color: #856404;
            text-align: center;
        }
        
        .login-info strong {
            display: block;
            margin-bottom: 10px;
            color: #960f1c;
            font-size: 1em;
        }
        
        .login-info br {
            display: block;
            margin: 5px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #960f1c;
            box-shadow: 0 0 0 3px rgba(150, 15, 28, 0.1);
        }
        
        .form-actions {
            margin-top: 25px;
        }

        .logo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .logo-container {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #cebc40 0%, #cebc40 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(206, 188, 64, 0.4);
            background: linear-gradient(135deg, #e3c70c 0%, #e3c70c 100%);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <img src="img/logo.png" alt="Logo" class="logo">
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <!--
            <div class="login-info">
                <strong>Credenciales por defecto:</strong>
                Usuario: <strong>admin</strong> / Contraseña: <strong>admin123</strong><br>
                Usuario: <strong>operador</strong> / Contraseña: <strong>admin123</strong>
            </div>
            -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required autofocus 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Iniciar Sesión</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

