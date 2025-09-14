<?php
// Script simple para crear usuario Editor
include 'includes/conexion.php';

echo "<h2>Creando Usuario Editor</h2>";

try {
    // 1. Actualizar ENUM
    $sql1 = "ALTER TABLE `usuarios` MODIFY `rol` ENUM('usuario','dueno','admin','almacen','Editor') DEFAULT 'usuario'";
    if ($conexion->query($sql1)) {
        echo "✓ ENUM actualizado<br>";
    } else {
        echo "⚠ ENUM: " . $conexion->error . "<br>";
    }

    // 2. Crear usuario Editor
    $nombre = 'Usuario Editor';
    $usuario = 'editor';
    $contrasena = 'editor123';
    $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);
    $rol = 'Editor';

    $sql2 = "INSERT INTO usuarios (nombre, usuario, contrasena, rol) VALUES (?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql2);
    $stmt->bind_param("ssss", $nombre, $usuario, $hash_contrasena, $rol);
    
    if ($stmt->execute()) {
        echo "✓ Usuario Editor creado<br>";
        echo "Usuario: editor<br>";
        echo "Contraseña: editor123<br>";
        echo "ID: " . $conexion->insert_id . "<br>";
    } else {
        echo "✗ Error: " . $conexion->error . "<br>";
    }

    // 3. Verificar usuarios Editor
    echo "<h3>Usuarios con rol Editor:</h3>";
    $result = $conexion->query("SELECT id, nombre, usuario, rol FROM usuarios WHERE rol = 'Editor'");
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Nombre: {$row['nombre']}, Usuario: {$row['usuario']}, Rol: {$row['rol']}<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<br><a href='index.php'>← Ir al login</a>";
?>

