<?php
// Script para crear usuario Editor (almacén)
// Ejecutar una sola vez para configurar el sistema

include 'includes/conexion.php';

try {
    // 1. Modificar el ENUM de roles para incluir 'Editor'
    $sql_alter = "ALTER TABLE `usuarios` MODIFY `rol` ENUM('usuario','dueno','admin','almacen','Editor') DEFAULT 'usuario'";
    if ($conexion->query($sql_alter)) {
        echo "✓ ENUM de roles actualizado correctamente<br>";
    } else {
        echo "⚠ Error al actualizar ENUM o ya estaba actualizado: " . $conexion->error . "<br>";
    }

    // 2. Crear usuario almacén con rol Editor
    $nombre = 'Usuario Almacén';
    $usuario = 'almacen';
    $contrasena = 'almacen123'; // Cambiar por una contraseña segura
    $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);
    $rol = 'Editor';

    // Verificar si ya existe un usuario 'almacen'
    $stmt_check = $conexion->prepare("SELECT id, rol FROM usuarios WHERE usuario = ?");
    $stmt_check->bind_param("s", $usuario);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        // Usuario existe, actualizar rol
        $user_data = $result->fetch_assoc();
        $stmt_update = $conexion->prepare("UPDATE usuarios SET rol = ? WHERE usuario = ?");
        $stmt_update->bind_param("ss", $rol, $usuario);
        
        if ($stmt_update->execute()) {
            echo "✓ Usuario 'almacen' actualizado al rol 'Editor'<br>";
            echo "  ID del usuario: " . $user_data['id'] . "<br>";
        } else {
            echo "✗ Error al actualizar usuario: " . $conexion->error . "<br>";
        }
    } else {
        // Usuario no existe, crearlo
        $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, usuario, contrasena, rol) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("ssss", $nombre, $usuario, $hash_contrasena, $rol);
        
        if ($stmt_insert->execute()) {
            echo "✓ Usuario 'almacen' creado con rol 'Editor'<br>";
            echo "  ID del usuario: " . $conexion->insert_id . "<br>";
            echo "  Credenciales: usuario='almacen', contraseña='almacen123'<br>";
            echo "  <strong>¡IMPORTANTE: Cambiar la contraseña después del primer login!</strong><br>";
        } else {
            echo "✗ Error al crear usuario: " . $conexion->error . "<br>";
        }
    }

    // 3. Mostrar usuarios con rol Editor
    echo "<br><h3>Usuarios con rol Editor:</h3>";
    $result_editors = $conexion->query("SELECT id, nombre, usuario, rol FROM usuarios WHERE rol = 'Editor'");
    
    if ($result_editors->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; padding: 5px;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th></tr>";
        while ($row = $result_editors->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['nombre'] . "</td>";
            echo "<td>" . $row['usuario'] . "</td>";
            echo "<td>" . $row['rol'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No se encontraron usuarios con rol Editor.";
    }

    echo "<br><br><strong>Configuración completada!</strong><br>";
    echo "<a href='index.php'>← Volver al sistema</a>";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}

// Cerrar conexión
$conexion->close();
?>

