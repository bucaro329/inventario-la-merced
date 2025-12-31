-- Base de datos para Sistema de Inventario La Merced
CREATE DATABASE IF NOT EXISTS inventario_la_merced CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventario_la_merced;

-- Tabla de categorías
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    rol ENUM('admin', 'operador') DEFAULT 'operador',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    unidad_medida VARCHAR(20) DEFAULT 'UNIDAD',
    categoria_id INT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    INDEX idx_codigo (codigo),
    INDEX idx_nombre (nombre),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de entradas de inventario
CREATE TABLE IF NOT EXISTS entradas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_entrada VARCHAR(50) UNIQUE NOT NULL,
    fecha_entrada DATETIME NOT NULL,
    observaciones TEXT,
    usuario_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_numero_entrada (numero_entrada),
    INDEX idx_fecha_entrada (fecha_entrada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de detalle de entradas
CREATE TABLE IF NOT EXISTS detalle_entradas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrada_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (entrada_id) REFERENCES entradas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    INDEX idx_entrada_id (entrada_id),
    INDEX idx_producto_id (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de salidas de inventario
CREATE TABLE IF NOT EXISTS salidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_salida VARCHAR(50) UNIQUE NOT NULL,
    fecha_salida DATETIME NOT NULL,
    motivo ENUM('uso_interno', 'ajuste', 'danio', 'perdida', 'otro') DEFAULT 'uso_interno',
    observaciones TEXT,
    usuario_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_numero_salida (numero_salida),
    INDEX idx_fecha_salida (fecha_salida),
    INDEX idx_motivo (motivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de detalle de salidas
CREATE TABLE IF NOT EXISTS detalle_salidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salida_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    FOREIGN KEY (salida_id) REFERENCES salidas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    INDEX idx_salida_id (salida_id),
    INDEX idx_producto_id (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de movimientos (Kardex)
CREATE TABLE IF NOT EXISTS movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    tipo_movimiento ENUM('entrada', 'salida') NOT NULL,
    referencia_id INT NOT NULL,
    referencia_tipo ENUM('entrada', 'salida') NOT NULL,
    cantidad INT NOT NULL,
    stock_antes INT NOT NULL,
    stock_despues INT NOT NULL,
    fecha_movimiento DATETIME NOT NULL,
    usuario_id INT,
    observaciones TEXT,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_producto_id (producto_id),
    INDEX idx_tipo_movimiento (tipo_movimiento),
    INDEX idx_fecha_movimiento (fecha_movimiento),
    INDEX idx_referencia (referencia_tipo, referencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar categorías de ejemplo
INSERT INTO categorias (nombre, descripcion) VALUES
('General', 'Categoría general para productos sin clasificación específica'),
('Electrónicos', 'Productos electrónicos y tecnológicos'),
('Alimentos', 'Productos alimenticios'),
('Limpieza', 'Productos de limpieza e higiene');

-- Insertar usuarios por defecto
-- NOTA: Las contraseñas se deben actualizar ejecutando actualizar_passwords.php
-- Contraseña por defecto: admin123
-- El hash se genera automáticamente al ejecutar el script
INSERT INTO usuarios (username, password, nombre_completo, email, rol) VALUES
('admin', '$2y$10$tvertgBFzQYjlahlYkJ3J.W2L7Vvq3pW7QmTXCzrsJoila0Mg/AUW', 'Administrador', 'admin@lamerced.com', 'admin'),
('operador', '$2y$10$tvertgBFzQYjlahlYkJ3J.W2L7Vvq3pW7QmTXCzrsJoila0Mg/AUW', 'Operador', 'operador@lamerced.com', 'operador');

-- Insertar algunos productos de ejemplo
INSERT INTO productos (codigo, nombre, descripcion, precio, stock, unidad_medida, categoria_id, estado) VALUES
('PROD001', 'Producto Ejemplo 1', 'Descripción del producto ejemplo 1', 25.50, 100, 'UNIDAD', 1, 'activo'),
('PROD002', 'Producto Ejemplo 2', 'Descripción del producto ejemplo 2', 15.75, 50, 'UNIDAD', 1, 'activo'),
('PROD003', 'Producto Ejemplo 3', 'Descripción del producto ejemplo 3', 30.00, 75, 'UNIDAD', 1, 'activo');
