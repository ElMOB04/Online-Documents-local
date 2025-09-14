<?php
include '../config/conexion.php';

echo "<h2>🔧 Configuración del Rol Editor</h2>";

try {
    // 1. Modificar la tabla para incluir el rol Editor
    echo "<h3>Paso 1: Modificando tabla usuarios...</h3>";
    $sql1 = "ALTER TABLE `usuarios` MODIFY `rol` ENUM('usuario','dueno','admin','almacen','Editor') DEFAULT 'usuario'";
    
    if ($conexion->query($sql1)) {
        echo "<p style='color: green;'>✅ Tabla usuarios modificada exitosamente</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ La tabla ya tenía el rol Editor o error: " . $conexion->error . "</p>";
    }

    // 2. Crear/actualizar usuario almacén
    echo "<h3>Paso 2: Configurando usuario almacén...</h3>";
    $nombre = "Usuario Almacén";
    $usuario = "almacen";
    $contrasena = password_hash("password", PASSWORD_DEFAULT); // Contraseña: password
    $rol = "Editor";
    
    $sql2 = "INSERT INTO `usuarios` (`nombre`, `usuario`, `contrasena`, `rol`) 
             VALUES (?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE 
                `rol` = ?, 
                `nombre` = ?";
    
    $stmt = $conexion->prepare($sql2);
    $stmt->bind_param("ssssss", $nombre, $usuario, $contrasena, $rol, $rol, $nombre);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ Usuario almacén configurado exitosamente</p>";
        echo "<ul>";
        echo "<li><strong>Usuario:</strong> almacen</li>";
        echo "<li><strong>Contraseña:</strong> password</li>";
        echo "<li><strong>Rol:</strong> Editor</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ Error al configurar usuario: " . $conexion->error . "</p>";
    }

    // 3. Actualizar usuarios existentes con rol 'almacen' a 'Editor'
    echo "<h3>Paso 3: Actualizando usuarios con rol almacén...</h3>";
    $sql3 = "UPDATE `usuarios` SET `rol` = 'Editor' WHERE `rol` = 'almacen'";
    
    if ($conexion->query($sql3)) {
        $affected = $conexion->affected_rows;
        echo "<p style='color: green;'>✅ Se actualizaron $affected usuario(s) de 'almacen' a 'Editor'</p>";
    } else {
        echo "<p style='color: red;'>❌ Error al actualizar usuarios: " . $conexion->error . "</p>";
    }

    // 4. Verificar resultados
    echo "<h3>📋 Verificación de Resultados:</h3>";
    
    echo "<h4>Usuarios con rol Editor:</h4>";
    $result = $conexion->query("SELECT id, nombre, usuario, rol FROM usuarios WHERE rol = 'Editor'");
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre'] ?? 'Sin nombre') . "</td>";
            echo "<td><strong>" . htmlspecialchars($row['usuario'] ?? 'Sin usuario') . "</strong></td>";
            echo "<td><span style='color: green; font-weight: bold;'>" . $row['rol'] . "</span></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ No se encontraron usuarios con rol Editor</p>";
    }
    
    echo "<h4>Estructura del campo rol:</h4>";
    $result = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'rol'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Tipo:</strong> " . $row['Type'] . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

$conexion->close();
?>

<div style="margin-top: 30px; padding: 15px; background: #e8f5e8; border: 1px solid #4CAF50; border-radius: 5px;">
    <h3>🎯 Próximos Pasos:</h3>
    <ol>
        <li>Inicia sesión con: <strong>almacen</strong> / <strong>password</strong></li>
        <li>Ve a cualquier pedido</li>
        <li>Verifica que aparezca el checkbox de "Comentario Interno"</li>
        <li>Prueba crear un comentario interno</li>
    </ol>
</div>

<br>
<a href="index.php" style="background: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">← Ir al Login</a>
<a href="verificar_roles_debug.php" style="background: #2196F3; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-left: 10px;">Ver Debug de Roles</a>
