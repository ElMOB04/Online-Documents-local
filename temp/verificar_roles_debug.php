<?php
session_start();
include 'includes/conexion.php';

echo "<h2>Debug - Verificación de Roles</h2>";

// Mostrar información de la sesión actual
echo "<h3>Sesión Actual:</h3>";
echo "<p><strong>Usuario ID:</strong> " . ($_SESSION['usuario_id'] ?? 'No definido') . "</p>";
echo "<p><strong>Rol:</strong> " . ($_SESSION['rol'] ?? 'No definido') . "</p>";
echo "<p><strong>Nombre:</strong> " . ($_SESSION['usuario_nombre'] ?? $_SESSION['nombre'] ?? 'No definido') . "</p>";

// Mostrar todos los usuarios y sus roles
echo "<h3>Todos los Usuarios en la Base de Datos:</h3>";
$query = "SELECT id, nombre, usuario, rol FROM usuarios ORDER BY rol, nombre";
$resultado = $conexion->query($query);

if ($resultado && $resultado->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th></tr>";
    
    while ($row = $resultado->fetch_assoc()) {
        $destacar = ($_SESSION['usuario_id'] ?? 0) == $row['id'] ? 'style="background-color: yellow;"' : '';
        echo "<tr $destacar>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nombre'] ?? 'Sin nombre') . "</td>";
        echo "<td>" . htmlspecialchars($row['usuario'] ?? 'Sin usuario') . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['rol']) . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No se encontraron usuarios o error en la consulta.</p>";
}

// Verificar la estructura de la tabla usuarios
echo "<h3>Estructura de la tabla usuarios (campo rol):</h3>";
$query = "SHOW COLUMNS FROM usuarios LIKE 'rol'";
$resultado = $conexion->query($query);

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        echo "<p><strong>Campo:</strong> " . $row['Field'] . "</p>";
        echo "<p><strong>Tipo:</strong> " . $row['Type'] . "</p>";
        echo "<p><strong>Default:</strong> " . ($row['Default'] ?? 'NULL') . "</p>";
    }
} else {
    echo "<p>Error al obtener información del campo rol.</p>";
}

// Verificar condición específica para comentarios internos
echo "<h3>Verificación de Condición para Comentarios Internos:</h3>";
$rol_actual = $_SESSION['rol'] ?? 'usuario';
$puede_comentario_interno = ($rol_actual === 'almacen' || $rol_actual === 'admin' || $rol_actual === 'dueno' || $rol_actual === 'Editor');

echo "<p><strong>Rol actual:</strong> '$rol_actual'</p>";
echo "<p><strong>¿Puede crear comentarios internos?:</strong> " . ($puede_comentario_interno ? 'SÍ' : 'NO') . "</p>";

// Verificar roles específicos
echo "<p><strong>Comparaciones individuales:</strong></p>";
echo "<ul>";
echo "<li>¿Es 'almacen'? " . ($rol_actual === 'almacen' ? 'SÍ' : 'NO') . "</li>";
echo "<li>¿Es 'admin'? " . ($rol_actual === 'admin' ? 'SÍ' : 'NO') . "</li>";
echo "<li>¿Es 'dueno'? " . ($rol_actual === 'dueno' ? 'SÍ' : 'NO') . "</li>";
echo "<li>¿Es 'Editor'? " . ($rol_actual === 'Editor' ? 'SÍ' : 'NO') . "</li>";
echo "</ul>";

$conexion->close();
?>

<br><br>
<a href="dashboard.php">← Volver al Dashboard</a>
