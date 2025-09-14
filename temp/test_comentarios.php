<?php
// Script de prueba para verificar el sistema de comentarios
include 'includes/conexion.php';

echo "<h2>Prueba del Sistema de Comentarios</h2>";

// 1. Verificar que existe al menos una solicitud
$result = $conexion->query("SELECT COUNT(*) as total FROM store_requests");
$total_solicitudes = $result->fetch_assoc()['total'];

echo "<h3>1. Verificación de Solicitudes</h3>";
echo "Total de solicitudes en la BD: $total_solicitudes<br>";

if ($total_solicitudes == 0) {
    echo "⚠ No hay solicitudes. Creando una solicitud de prueba...<br>";
    
    // Crear una solicitud de prueba
    $stmt = $conexion->prepare("INSERT INTO store_requests (user_id, title, description, status) VALUES (?, ?, ?, ?)");
    $user_id = 1; // Asumiendo que existe un usuario con ID 1
    $title = "Solicitud de Prueba";
    $description = "Esta es una solicitud de prueba para verificar comentarios";
    $status = "pendiente";
    
    if ($stmt->bind_param("isss", $user_id, $title, $description, $status) && $stmt->execute()) {
        echo "✓ Solicitud de prueba creada con ID: " . $conexion->insert_id . "<br>";
    } else {
        echo "✗ Error al crear solicitud: " . $conexion->error . "<br>";
    }
}

// 2. Verificar usuarios con diferentes roles
echo "<h3>2. Verificación de Usuarios</h3>";
$result = $conexion->query("SELECT id, nombre, usuario, rol FROM usuarios ORDER BY rol");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Nombre: {$row['nombre']}, Usuario: {$row['usuario']}, Rol: {$row['rol']}<br>";
}

// 3. Verificar permisos de comentarios
echo "<h3>3. Verificación de Permisos</h3>";
$roles_con_permisos = ['admin', 'dueno', 'almacen', 'Editor'];
echo "Roles que pueden comentar: " . implode(', ', $roles_con_permisos) . "<br>";

// 4. Verificar estructura de la tabla de comentarios
echo "<h3>4. Verificación de Estructura de BD</h3>";
$result = $conexion->query("DESCRIBE store_request_comments");
echo "<table border='1'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// 5. Mostrar solicitudes disponibles para probar
echo "<h3>5. Solicitudes Disponibles para Probar</h3>";
$result = $conexion->query("SELECT sr.id, sr.title, sr.status, u.nombre as solicitante FROM store_requests sr JOIN usuarios u ON sr.user_id = u.id ORDER BY sr.id DESC LIMIT 5");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Título</th><th>Estado</th><th>Solicitante</th><th>Acción</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['title']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td>{$row['solicitante']}</td>";
    echo "<td><a href='pedido_detalle.php?id={$row['id']}' target='_blank'>Ver Detalle</a></td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>Instrucciones para Probar:</h3>";
echo "1. Haz clic en 'Ver Detalle' de cualquier solicitud<br>";
echo "2. Intenta agregar un comentario<br>";
echo "3. Si hay error, revisa los mensajes de error en la página<br>";
echo "4. Verifica que el comentario aparezca en la lista<br>";

echo "<br><a href='index.php'>← Volver al sistema</a>";
?>

