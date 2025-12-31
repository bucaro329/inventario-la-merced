# Sistema de Inventario La Merced

Sistema completo de gestión de inventario desarrollado en PHP con control de entradas, salidas, kardex y reportes.

## 🎯 Características Principales

### ✅ Módulos Implementados

1. **Productos**
   - Crear / editar / eliminar productos
   - Código o SKU único
   - Nombre, descripción, precio
   - Unidad de medida (UNIDAD, KG, LITRO, CAJA, METRO)
   - Stock actual
   - Estado (activo / inactivo)
   - Asignación de categorías

2. **Categorías**
   - Gestión de categorías de productos
   - Clasificación de inventario

3. **Entradas de Inventario**
   - Registro de ingreso de productos
   - Cantidad y precio unitario
   - Fecha automática
   - Observaciones
   - Usuario que registró
   - **Aumenta el stock automáticamente**
   - Registra movimiento en kardex

4. **Salidas de Inventario**
   - Registro de salida de productos
   - Cantidad
   - Motivo (uso interno, ajuste, daño, pérdida, otro)
   - Fecha automática
   - Usuario
   - Observaciones
   - **Disminuye el stock automáticamente**
   - **No es venta, solo control interno**
   - Registra movimiento en kardex

5. **Kardex / Movimientos**
   - Historial completo de entradas y salidas
   - Filtros por producto, tipo de movimiento y fecha
   - Muestra stock antes y después de cada movimiento
   - Referencia a entrada/salida original
   - Usuario que realizó el movimiento

6. **Usuarios**
   - Sistema de login con autenticación
   - Roles: Administrador y Operador
   - Control de acceso por roles
   - Gestión de usuarios (solo administradores)

7. **Reportes**
   - Stock actual de productos
   - Productos con bajo inventario (stock < 10)
   - Entradas por fecha
   - Salidas por fecha
   - Estadísticas generales
   - Impresión de reportes

## 📋 Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior (o MariaDB)
- Servidor web (Apache/Nginx) o WAMP/XAMPP
- Extensión PHP: mysqli, password_hash (incluidas por defecto)

## 🚀 Instalación

### 1. Copiar archivos
Copia todos los archivos a tu servidor web:
```
C:\Wamp64\www\inventario-la-merced\
```

### 2. Crear la base de datos
- Abre phpMyAdmin o tu cliente MySQL
- Importa el archivo `database.sql` o ejecuta el script manualmente
- El script creará:
  - Base de datos `inventario_la_merced`
  - Todas las tablas necesarias
  - Usuarios por defecto
  - Categorías de ejemplo
  - Productos de ejemplo

### 3. Configurar conexión
Edita el archivo `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Tu usuario MySQL
define('DB_PASS', '');            // Tu contraseña MySQL
define('DB_NAME', 'inventario_la_merced');
```

### 4. Acceder al sistema
- Abre tu navegador: `http://localhost/inventario-la-merced/`
- Serás redirigido al login

### 5. Credenciales por defecto
- **Administrador:**
  - Usuario: `admin`
  - Contraseña: `admin123`

- **Operador:**
  - Usuario: `operador`
  - Contraseña: `admin123`

⚠️ **IMPORTANTE:** Cambia las contraseñas después del primer acceso.

## 📁 Estructura del Proyecto

```
inventario-la-merced/
├── assets/
│   └── css/
│       └── style.css          # Estilos del sistema
├── config/
│   ├── database.php          # Configuración de base de datos
│   └── auth.php              # Sistema de autenticación
├── includes/
│   ├── header.php            # Cabecera común
│   └── footer.php            # Pie de página común
├── index.php                  # Dashboard principal
├── login.php                  # Página de login
├── logout.php                 # Cerrar sesión
├── productos.php              # Gestión de productos
├── categorias.php             # Gestión de categorías
├── entradas.php               # Entradas de inventario
├── salidas.php                # Salidas de inventario
├── kardex.php                 # Movimientos / Kardex
├── reportes.php               # Reportes del sistema
├── usuarios.php               # Gestión de usuarios (solo admin)
├── detalle_entrada.php        # Detalle de entrada
├── detalle_salida.php         # Detalle de salida
├── database.sql               # Script de base de datos
└── README.md                  # Este archivo
```

## 🗄️ Base de Datos

### Tablas principales:
- **categorias:** Clasificación de productos
- **usuarios:** Usuarios del sistema
- **productos:** Catálogo de productos
- **entradas:** Registro de entradas
- **detalle_entradas:** Detalle de productos en entradas
- **salidas:** Registro de salidas
- **detalle_salidas:** Detalle de productos en salidas
- **movimientos:** Kardex completo (historial de movimientos)

## 🔐 Sistema de Usuarios

### Roles:
- **Administrador:** Acceso completo, puede gestionar usuarios
- **Operador:** Acceso a módulos operativos (no puede gestionar usuarios)

### Seguridad:
- Contraseñas encriptadas con bcrypt
- Sesiones PHP seguras
- Control de acceso por roles
- Protección contra SQL injection (prepared statements)

## 📊 Funcionalidades Detalladas

### Gestión de Productos
- CRUD completo (Crear, Leer, Actualizar, Eliminar)
- Búsqueda y filtrado por estado
- Alertas visuales de stock bajo
- Asignación de categorías

### Entradas de Inventario
- Agregar múltiples productos en una entrada
- Precio unitario por producto
- Actualización automática de stock
- Registro en kardex automático
- Historial de entradas

### Salidas de Inventario
- Agregar múltiples productos en una salida
- Selección de motivo (uso interno, ajuste, daño, pérdida, otro)
- Validación de stock disponible
- Actualización automática de stock
- Registro en kardex automático
- Historial de salidas

### Kardex
- Vista completa de todos los movimientos
- Filtros avanzados:
  - Por producto
  - Por tipo (entrada/salida)
  - Por rango de fechas
- Muestra stock antes y después
- Referencia a documento original

### Reportes
- **Stock Actual:** Listado completo con estado
- **Bajo Stock:** Productos con stock menor a 10
- **Entradas por Fecha:** Entradas en un período
- **Salidas por Fecha:** Salidas en un período
- Estadísticas generales
- Impresión de reportes

## 🎨 Interfaz

- Diseño moderno y responsive
- Colores intuitivos (verde para entradas, rojo para salidas)
- Alertas visuales para stock bajo
- Navegación intuitiva
- Tablas ordenables y filtrables

## 🔧 Personalización

### Cambiar límite de stock bajo:
Edita la consulta en `reportes.php` y `index.php`:
```php
WHERE stock < 10  // Cambia 10 por el valor deseado
```

### Agregar unidades de medida:
Edita el select en `productos.php`:
```html
<option value="NUEVA_UNIDAD">NUEVA_UNIDAD</option>
```

### Cambiar motivos de salida:
Edita el enum en `database.sql` y actualiza los arrays en `salidas.php` y `reportes.php`.

## 🐛 Solución de Problemas

### Error de conexión a base de datos:
- Verifica las credenciales en `config/database.php`
- Asegúrate de que MySQL esté corriendo
- Verifica que la base de datos exista

### Error de permisos:
- Verifica que el usuario MySQL tenga permisos
- Revisa los permisos de archivos PHP

### No se actualiza el stock:
- Verifica que las transacciones estén habilitadas (InnoDB)
- Revisa los logs de errores de PHP

## 📝 Notas Importantes

- El sistema **NO** genera facturas fiscales
- Las salidas son para control interno únicamente
- El stock se actualiza automáticamente
- Los productos eliminados se marcan como inactivos (soft delete)
- Todos los movimientos quedan registrados en el kardex

## 📞 Soporte

Para problemas o consultas:
1. Revisa los logs de errores de PHP
2. Verifica la configuración de la base de datos
3. Asegúrate de que todas las extensiones PHP estén habilitadas

## 📄 Licencia

Sistema desarrollado para uso interno de La Merced.

---

**Versión:** 2.0  
**Última actualización:** 2024
