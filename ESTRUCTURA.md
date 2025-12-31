# Estructura del Proyecto - Sistema de Inventario La Merced

## 📁 Organización de Carpetas

```
inventario-la-merced/
├── assets/
│   └── css/
│       └── style.css          # Estilos del sistema
│
├── config/
│   ├── auth.php               # Sistema de autenticación
│   ├── database.php           # Configuración de base de datos
│   └── paths.php               # Configuración de rutas (helper)
│
├── exports/                    # Archivos de exportación
│   ├── productos_excel.php    # Exportar productos a Excel
│   ├── productos_pdf.php      # Exportar productos a PDF
│   ├── kardex_excel.php       # Exportar kardex a Excel
│   └── kardex_pdf.php         # Exportar kardex a PDF
│
├── imports/                    # Archivos de importación
│   └── productos.php          # Importar productos desde CSV/Excel
│
├── includes/                   # Archivos compartidos
│   ├── header.php             # Cabecera común (sidebar)
│   └── footer.php             # Pie de página común
│
├── index.php                   # Dashboard principal
├── login.php                   # Página de login
├── logout.php                  # Cerrar sesión
│
├── productos.php               # Gestión de productos
├── categorias.php              # Gestión de categorías
├── entradas.php                # Entradas de inventario
├── salidas.php                 # Salidas de inventario
├── kardex.php                  # Movimientos / Kardex
├── reportes.php                # Reportes del sistema
├── usuarios.php                # Gestión de usuarios (solo admin)
│
├── detalle_entrada.php         # Detalle de entrada
├── detalle_salida.php          # Detalle de salida
├── recibo.php                  # Recibo de salida (legacy)
│
├── actualizar_passwords.php    # Script para actualizar contraseñas
├── database.sql                # Script de base de datos
├── README.md                   # Documentación principal
└── ESTRUCTURA.md               # Este archivo
```

## 🔗 Rutas y Enlaces

### Archivos Principales (Raíz)
Todos los archivos principales están en la raíz y usan rutas relativas:
- `index.php`, `productos.php`, `entradas.php`, etc.

### Archivos de Exportación
Ubicados en `exports/`:
- `exports/productos_excel.php`
- `exports/productos_pdf.php`
- `exports/kardex_excel.php`
- `exports/kardex_pdf.php`

### Archivos de Importación
Ubicados en `imports/`:
- `imports/productos.php`

### Includes
Ubicados en `includes/`:
- `includes/header.php` - Usa `__DIR__` para rutas relativas
- `includes/footer.php`

## 📝 Notas Importantes

1. **Rutas Relativas**: Todos los archivos usan rutas relativas desde su ubicación
2. **Exports/Imports**: Usan `dirname(__DIR__)` para acceder a `config/` e `includes/`
3. **Archivos Principales**: Usan rutas directas como `config/`, `includes/`, `exports/`, `imports/`
4. **Assets**: Siempre referenciados como `assets/css/style.css` desde la raíz

## 🎨 Colores del Sistema

- **Rojo Principal**: `#960f1c`
- **Amarillo Secundario**: `#f7bc00`
- **Rojo Oscuro**: `#7a0c16`
- **Amarillo Oscuro**: `#d4a000`

## 🔐 Sistema de Autenticación

- Login: `login.php`
- Logout: `logout.php`
- Configuración: `config/auth.php`
- Requiere login en todos los módulos excepto `login.php`

