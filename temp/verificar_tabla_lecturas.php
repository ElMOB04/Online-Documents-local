<?php
include 'includes/conexion.php';

// Verificar si la tabla existe
$result = $conexion->query("SHOW TABLES LIKE 'lecturas_documentos'");

if ($result->num_rows == 0) {
    // La tabla no existe, crearla
    $sql = "CREATE TABLE IF NOT EXISTS lecturas_documentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        documento_id INT NOT NULL,
        usuario_id INT NOT NULL,
        fecha_lectura DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (documento_id) REFERENCES documentos(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conexion->query($sql)) {
        echo "Tabla lecturas_documentos creada correctamente.";
    } else {
        echo "Error al crear la tabla: " . $conexion->error;
    }
} else {
    echo "La tabla lecturas_documentos ya existe.";
}

// Verificar la estructura de la tabla
$result = $conexion->query("DESCRIBE lecturas_documentos");
if ($result) {
    echo "<br><br>Estructura de la tabla lecturas_documentos:<br>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
} else {
    echo "<br>Error al obtener la estructura de la tabla: " . $conexion->error;
}
?> 